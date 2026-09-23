<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;

uses(LazilyRefreshDatabase::class);

test('guests see the French login form with local assets and no public registration', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Bienvenue dans')
        ->assertSee('Adresse e-mail')
        ->assertSee('vendor/bootstrap/css/bootstrap.min.css')
        ->assertSee('css/auth-login.css')
        ->assertDontSee('cdn.')
        ->assertDontSee('Créer un compte');

    $this->assertGuest();
});

test('the dashboard is inaccessible without authentication', function () {
    $this->get(route('dashboard'))->assertRedirectToRoute('login');
});

test('valid credentials open the dashboard with email case and whitespace normalized', function () {
    $user = User::factory()->create(['email' => 'person@example.test']);

    $this->post(route('login.store'), ['email' => '  PERSON@EXAMPLE.TEST  ', 'password' => 'password'])
        ->assertRedirectToRoute('dashboard')
        ->assertSessionHasNoErrors();

    $this->assertAuthenticatedAs($user);
});

test('incorrect credentials produce the same message for existing and unknown accounts', function (bool $existing) {
    if ($existing) {
        User::factory()->create(['email' => 'person@example.test']);
    }

    $this->from(route('login'))->post(route('login.store'), [
        'email' => 'person@example.test',
        'password' => 'wrong-secret',
    ])->assertRedirectToRoute('login')
        ->assertSessionHasErrors(['email' => 'L’adresse e-mail ou le mot de passe est incorrect.'])
        ->assertSessionHasInput('email', 'person@example.test')
        ->assertSessionMissing('_old_input.password');

    $this->assertGuest();
})->with(['known account' => true, 'unknown account' => false]);

test('invalid login fields are rejected with French messages and status 422', function (array $input, string $field, string $message) {
    $this->postJson(route('login.store'), $input)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field => $message]);

    $this->assertGuest();
})->with([
    'missing email' => [['password' => 'secret'], 'email', 'Le champ adresse e-mail est obligatoire.'],
    'malformed email' => [['email' => 'not-email', 'password' => 'secret'], 'email', 'Saisissez une adresse e-mail valide.'],
    'email array' => [['email' => ['invalid'], 'password' => 'secret'], 'email', 'Le champ adresse e-mail doit être du texte.'],
    'long email' => [['email' => str_repeat('a', 245).'@example.test', 'password' => 'secret'], 'email', 'Le champ adresse e-mail ne doit pas dépasser 254 caractères.'],
    'missing password' => [['email' => 'person@example.test'], 'password', 'Le champ mot de passe est obligatoire.'],
    'password array' => [['email' => 'person@example.test', 'password' => ['secret']], 'password', 'Le champ mot de passe doit être du texte.'],
    'long password' => [['email' => 'person@example.test', 'password' => str_repeat('a', 4097)], 'password', 'Le champ mot de passe ne doit pas dépasser 4096 caractères.'],
    'invalid remember' => [['email' => 'person@example.test', 'password' => 'secret', 'remember' => 'invalid'], 'remember', 'La valeur du champ rester connecté est invalide.'],
]);

test('remember me is issued only when selected', function (bool $remember) {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
        'remember' => $remember,
    ])->assertRedirectToRoute('dashboard');

    $this->assertAuthenticatedAs($user);
    $cookie = Auth::guard('web')->getRecallerName();

    if ($remember) {
        $response->assertCookie($cookie);
    } else {
        $response->assertCookieMissing($cookie);
    }
})->with(['selected' => true, 'not selected' => false]);

test('authenticated users are redirected away from the login form', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('login'))->assertRedirectToRoute('dashboard');
});

test('the dashboard shows the signed in name safely and a logout form', function () {
    $user = User::factory()->create(['name' => '<script>alert(1)</script>']);

    $response = $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee($user->name)
        ->assertDontSee($user->name, false)
        ->assertSee('Se déconnecter');

    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

test('logout clears authentication and the session and returns to login', function () {
    $this->actingAs(User::factory()->create())->withSession(['private-marker' => 'private'])
        ->post(route('logout'))
        ->assertRedirectToRoute('login')
        ->assertSessionMissing('private-marker');

    $this->assertGuest();
});

test('logout cannot be triggered by a GET link', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/logout')->assertMethodNotAllowed();

    $this->assertAuthenticatedAs($user);
});

test('public account creation is unavailable', function () {
    $this->post('/register', ['name' => 'Unexpected', 'email' => 'new@example.test', 'password' => 'secret'])
        ->assertNotFound();

    $this->assertDatabaseCount('users', 0);
});

test('old email input is escaped and a password is never repopulated', function () {
    $this->withSession(['_old_input' => [
        'email' => '"><script>alert(1)</script>',
        'password' => 'never-render-this-secret',
    ]])->get(route('login'))
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertDontSee('never-render-this-secret');
});

test('malformed old input cannot break the login page', function () {
    $this->withSession(['_old_input' => ['email' => ['invalid']]])
        ->get(route('login'))->assertOk();
});

test('five failed attempts block even valid credentials until the cooldown expires', function () {
    $user = User::factory()->create(['email' => 'limited@example.test']);
    $this->freezeTime();

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson(route('login.store'), ['email' => $user->email, 'password' => 'wrong'])
            ->assertUnprocessable();
    }

    $this->postJson(route('login.store'), ['email' => 'LIMITED@EXAMPLE.TEST', 'password' => 'password'])
        ->assertTooManyRequests()
        ->assertJsonValidationErrors(['email' => 'Trop de tentatives. Réessayez dans 60 secondes.']);

    $this->assertGuest();
    $this->travel(61)->seconds();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirectToRoute('dashboard');

    $this->assertAuthenticatedAs($user);
});

test('asynchronous login returns its destination and records the authenticated session', function (?string $intended, string $destination) {
    URL::forceRootUrl('http://localhost');
    $user = User::factory()->create(['email' => 'async@example.test']);
    if ($intended !== null) {
        $this->withSession(['url.intended' => $intended]);
    }

    $this->postJson(route('login.store'), [
        'email' => ' ASYNC@EXAMPLE.TEST ',
        'password' => 'password',
        'remember' => true,
    ])->assertOk()
        ->assertExactJson(['two_factor' => false, 'redirect' => $destination])
        ->assertSessionMissing('url.intended')
        ->assertCookie(Auth::guard('web')->getRecallerName());

    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseHas('user_login_histories', ['user_id' => $user->id]);
    $this->assertDatabaseCount('user_login_histories', 1);
})->with([
    'default dashboard' => [null, 'http://localhost'],
    'intended page' => ['http://localhost/?view=video', 'http://localhost/?view=video'],
]);

test('asynchronous invalid credentials return a generic 422 without creating login history', function (bool $existing) {
    if ($existing) {
        User::factory()->create(['email' => 'async-invalid@example.test']);
    }

    $this->postJson(route('login.store'), [
        'email' => 'async-invalid@example.test',
        'password' => 'wrong-secret',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['email' => 'L’adresse e-mail ou le mot de passe est incorrect.'])
        ->assertJsonMissingPath('redirect')
        ->assertDontSee('wrong-secret');

    $this->assertGuest();
    $this->assertDatabaseCount('user_login_histories', 0);
})->with(['known account' => true, 'unknown account' => false]);
