<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('guests cannot retrieve the map configuration', function () {
    config(['services.google_maps.api_key' => 'maps-test-browser-key']);
    $this->get(route('dashboard'))->assertRedirectToRoute('login')->assertDontSee('maps-test-browser-key');
});

test('the dashboard remains available when maps is not configured', function () {
    config(['services.google_maps.api_key' => '']);
    $this->actingAs(User::factory()->create(['role' => 'superadmin']))->get(route('dashboard'))
        ->assertOk()->assertSee('La carte sera disponible après configuration.')
        ->assertViewHas('googleMap', fn (array $map): bool => $map['apiKey'] === '');
});

test('the map loads authorized real positions instead of embedding demo coordinates', function () {
    config(['services.google_maps.api_key' => 'maps-test-browser-key', 'services.google_maps.map_id' => 'maps-test-id']);
    $this->actingAs(User::factory()->create(['role' => 'superadmin']))->get(route('dashboard'))
        ->assertOk()->assertDontSee('POSITIONS DE DÉMONSTRATION')
        ->assertViewHas('googleMap', function (array $map): bool {
            expect($map['apiKey'])->toBe('maps-test-browser-key')->and($map['mapId'])->toBe('maps-test-id')
                ->and($map['positionsUrl'])->toBe(route('map.vehicles'))->and($map['allowed'])->toBeTrue()
                ->and($map)->not->toHaveKey('vehicles');

            return true;
        });
});
