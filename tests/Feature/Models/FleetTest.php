<?php

use App\Models\Fleet;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('fleet visibility is restricted to the assigned fleet except for active superadmins', function (string $role, string $status, bool $assigned, int $count) {
    $own = Fleet::create(['name' => 'Own fleet', 'code' => 'OWN']);
    $other = Fleet::create(['name' => 'Other fleet', 'code' => 'OTHER']);
    $user = User::factory()->make(['role' => $role, 'status' => $status, 'fleet_id' => $assigned ? $own->id : null]);

    $ids = Fleet::visibleTo($user)->pluck('id');

    expect($ids)->toHaveCount($count);
    if ($count === 1) {
        expect($ids->all())->toBe([$own->id]);
    }
})->with([
    'superadmin sees all fleets' => ['superadmin', 'active', false, 2],
    'admin sees own fleet only' => ['admin', 'active', true, 1],
    'user sees own fleet only' => ['user', 'active', true, 1],
    'unassigned admin sees none' => ['admin', 'active', false, 0],
    'disabled superadmin sees none' => ['superadmin', 'disabled', false, 0],
]);
