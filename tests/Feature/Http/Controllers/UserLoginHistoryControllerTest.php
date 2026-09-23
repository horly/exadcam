<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('login history is paginated searchable sortable and scoped to the requested user', function () {
    $actor = User::factory()->create(['role' => UserRole::Superadmin]);
    $user = User::factory()->create();
    foreach (range(1, 7) as $index) {
        $user->loginHistories()->create(['device' => 'Appareil '.$index, 'ip_address' => '10.0.0.'.$index, 'logged_in_at' => '2026-09-16 10:00:0'.$index]);
    }
    $actor->loginHistories()->create(['device' => 'Secret other device', 'logged_in_at' => now()]);
    $url = '/users/'.$user->id.'/login-history';
    $result = $this->actingAs($actor)->getJson($url)->assertOk()->assertJsonPath('meta.total', 7)->assertJsonPath('meta.last_page', 2);
    expect($result->json('html'))->toContain('Appareil 7')->not->toContain('Appareil 1', 'Secret other device');
    $this->getJson($url.'?search=10.0.0.3')->assertOk()->assertJsonPath('meta.total', 1)->assertSee('Appareil 3');
    $this->getJson($url.'?sort=logged_in_at&direction=asc')->assertSee('Appareil 1')->assertDontSee('Appareil 7');
    $this->getJson($url.'?page=2')->assertSee('Appareil 1')->assertDontSee('Appareil 7');
    $this->getJson($url.'?sort=password')->assertUnprocessable()->assertJsonValidationErrors(['sort']);
});

test('login history escapes device and address fields and supports an empty result', function () {
    $actor = User::factory()->create(['role' => UserRole::Superadmin]);
    $this->actingAs($actor)->getJson('/users/'.$actor->id.'/login-history')->assertSee('Aucun historique');
    $actor->loginHistories()->create(['device' => '<script>bad()</script>', 'ip_address' => '<b>bad</b>', 'logged_in_at' => now()]);
    $response = $this->getJson('/users/'.$actor->id.'/login-history');
    expect($response->json('html'))->toContain('&lt;script&gt;', '&lt;b&gt;')->not->toContain('<script>bad()', '<b>bad');
});
