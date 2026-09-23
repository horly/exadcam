<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(LazilyRefreshDatabase::class);

test('guests can select either language and read a fully translated sign-in form', function (string $locale, string $heading, string $email, string $help) {
    $this->post(route('locale.update', $locale))
        ->assertRedirectToRoute('login')
        ->assertSessionHas('locale', $locale)
        ->assertCookie('exadcam_locale', $locale);

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('lang="'.$locale.'"', false)
        ->assertSee($heading)
        ->assertSee($email)
        ->assertSee($help);

    $this->assertGuest();
})->with([
    'French' => ['fr', 'Bienvenue dans', 'Adresse e-mail', 'Besoin d’aide pour vous connecter ?'],
    'English' => ['en', 'Welcome to', 'Email address', 'Need help signing in?'],
]);

test('unsupported languages are rejected without altering the preference', function () {
    $this->withSession(['locale' => 'en'])
        ->post('/language/de')
        ->assertNotFound()
        ->assertSessionHas('locale', 'en')
        ->assertCookieMissing('exadcam_locale');
});

test('a locale preference cannot be changed through a get request', function () {
    $this->get('/language/en')->assertMethodNotAllowed()->assertCookieMissing('exadcam_locale');
});

test('language selection ignores external redirect input and preserves the intended page', function () {
    $this->withSession(['url.intended' => route('dashboard')])
        ->withHeader('Referer', 'https://example.test/untrusted')
        ->post(route('locale.update', 'en'), ['redirect' => 'https://example.test/untrusted'])
        ->assertRedirectToRoute('login')
        ->assertSessionHas('url.intended', route('dashboard'));
});

test('a returning visitor gets the saved cookie language without an existing session', function () {
    $this->withCookie('exadcam_locale', 'en')->get(route('login'))
        ->assertOk()
        ->assertSee('Welcome to')
        ->assertSee('Keep me signed in');
});

test('invalid saved locale values fall back to French', function (array $session, array $cookies) {
    $this->withSession($session)->withCookies($cookies)->get(route('login'))
        ->assertOk()
        ->assertSee('lang="fr"', false)
        ->assertSee('Bienvenue dans');
})->with([
    'unknown cookie' => [[], ['exadcam_locale' => 'de']],
    'unknown session' => [['locale' => 'invalid'], []],
    'array session' => [['locale' => ['en']], []],
]);

test('a selected session language takes precedence over an older cookie', function () {
    $this->withSession(['locale' => 'fr'])->withCookie('exadcam_locale', 'en')
        ->get(route('login'))->assertOk()->assertSee('Bienvenue dans');
});

test('an authenticated user keeps their session when selecting a language and the preference survives logout', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('locale.update', 'en'))
        ->assertRedirectToRoute('dashboard')
        ->assertCookie('exadcam_locale', 'en');

    $this->assertAuthenticatedAs($user);
    $this->withCookie('exadcam_locale', 'en')->post(route('logout'))
        ->assertRedirectToRoute('login')
        ->assertSessionMissing('locale')
        ->assertCookieMissing('exadcam_locale');
    $this->assertGuest();

    Auth::forgetGuards();
    $this->get(route('login'))->assertOk()->assertSee('Welcome to');
});

test('login failures are translated and do not retain the submitted password', function () {
    $this->withSession(['locale' => 'en'])->from(route('login'))
        ->post(route('login.store'), ['email' => 'unknown@example.test', 'password' => 'wrong-secret'])
        ->assertRedirectToRoute('login')
        ->assertSessionHasErrors(['email' => 'The email address or password is incorrect.'])
        ->assertSessionHasInput('email', 'unknown@example.test')
        ->assertSessionMissing('_old_input.password');
});

test('validation errors use the selected language', function () {
    $this->withCredentials()->withCookie('exadcam_locale', 'en')->postJson(route('login.store'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'email' => 'The email address field is required.',
            'password' => 'The password field is required.',
        ]);
});

test('expired form sessions display the recovery page in the saved language', function () {
    $this->app->detectEnvironment(fn () => 'local');

    $this->withCookie('exadcam_locale', 'en')->post(route('login.store'), [])
        ->assertStatus(419)
        ->assertSee('Session expired')
        ->assertSee('Back to sign in');
});

test('changing language requires a valid csrf token', function () {
    $this->app->detectEnvironment(fn () => 'local');

    $this->withSession(['locale' => 'fr'])->post(route('locale.update', 'en'))
        ->assertStatus(419)
        ->assertSessionHas('locale', 'fr')
        ->assertCookieMissing('exadcam_locale');
});
