<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    Route::get('/access-test/admin', fn () => response('allowed'))
        ->middleware(['web', 'auth', 'active', 'superadmin']);
    Route::get('/access-test/map', fn () => response('allowed'))
        ->middleware(['web', 'auth', 'active', 'client.permission:map.view']);
});

test('guests cannot access an administrative route', function () {
    $this->get('/access-test/admin')->assertRedirectToRoute('login');
});

test('only a superadmin can enter the administrative route', function (string $role, int $status) {
    $this->actingAs(User::factory()->create(['role' => $role]))
        ->get('/access-test/admin')->assertStatus($status);
})->with([
    'superadmin' => ['superadmin', 200],
    'admin' => ['admin', 403],
    'user' => ['user', 403],
]);

test('client permissions are enforced by the route middleware', function (array $permissions, int $status) {
    $this->actingAs(User::factory()->create(['permissions' => $permissions]))
        ->get('/access-test/map')->assertStatus($status);
})->with([
    'allowed' => [['map.view'], 200],
    'another permission' => [['reports.generate'], 403],
    'no permissions' => [[], 403],
]);

test('disabling an account invalidates an already authenticated session', function () {
    $user = User::factory()->create(['role' => 'superadmin', 'status' => 'disabled']);

    $this->actingAs($user)->withSession(['private-marker' => 'secret'])
        ->get(route('dashboard'))
        ->assertRedirectToRoute('login')
        ->assertSessionMissing('private-marker')
        ->assertSessionHasErrors(['email' => 'Ce compte est désactivé. Contactez votre administrateur.']);

    $this->assertGuest();
});

test('inactive accounts cannot sign in even with the correct password', function (array $attributes) {
    $user = User::factory()->create($attributes);

    $this->from(route('login'))->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirectToRoute('login')
        ->assertSessionHasErrors(['email' => 'L’adresse e-mail ou le mot de passe est incorrect.']);

    $this->assertGuest();
    $this->assertDatabaseCount('user_login_histories', 0);
})->with([
    'disabled status' => [['status' => 'disabled']],
    'disabled timestamp' => [['disabled_at' => '2026-01-01 12:00:00']],
]);

test('a successful login records the device and retains the intended redirect', function () {
    $user = User::factory()->create(['role' => 'superadmin']);
    $this->withSession(['url.intended' => route('dashboard').'#fleet'])
        ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/131.0.0.0'])
        ->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard').'#fleet');

    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseCount('user_login_histories', 1);
    $this->assertDatabaseHas('user_login_histories', [
        'user_id' => $user->id,
        'device' => 'Chrome on Windows',
        'ip_address' => '127.0.0.1',
    ]);
});
