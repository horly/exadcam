<?php

use App\Enums\UserRole;
use App\Models\Fleet;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('client rights require an active account and a known permission', function (string $role, string $status, array $permissions, string $permission, bool $allowed) {
    $user = User::factory()->make(['role' => $role, 'status' => $status, 'permissions' => $permissions]);
    expect($user->hasClientPermission($permission))->toBe($allowed);
})->with([
    'superadmin inherits known rights' => ['superadmin', 'active', [], 'map.view', true],
    'admin inherits known rights' => ['admin', 'active', [], 'reports.generate', true],
    'user receives assigned rights' => ['user', 'active', ['map.view'], 'map.view', true],
    'user has no implicit rights' => ['user', 'active', [], 'map.view', false],
    'disabled superadmin has no rights' => ['superadmin', 'disabled', [], 'map.view', false],
    'unknown right fails closed' => ['superadmin', 'active', [], 'unknown.operation', false],
    'unknown stored right fails closed' => ['user', 'active', ['unknown.operation'], 'unknown.operation', false],
]);

test('user ownership and fleet permissions survive persistence and unlink safely', function () {
    $subscription = Subscription::create(['name' => 'Test company', 'slug' => 'test-company']);
    $fleet = Fleet::create(['subscription_id' => $subscription->id, 'name' => 'Test fleet', 'code' => 'FLEET']);
    $creator = User::factory()->create(['role' => UserRole::Superadmin]);
    $user = User::factory()->create([
        'subscription_id' => $subscription->id,
        'fleet_id' => $fleet->id,
        'created_by' => $creator->id,
        'permissions' => ['map.view'],
        'phone' => '+243000000000',
        'address' => 'Test address',
        'profile_photo_path' => 'profiles/example.png',
    ]);
    $user->fleets()->attach($fleet, ['permission' => 'manager']);

    expect($user->fresh()->creator->id)->toBe($creator->id);
    expect($user->fresh()->subscription->id)->toBe($subscription->id);
    expect($user->fresh()->fleet->id)->toBe($fleet->id);
    expect($user->fresh()->fleets->sole()->pivot->permission)->toBe('manager');
    expect($user->fresh()->permissions)->toBe(['map.view']);
    expect($user->fresh()->phone)->toBe('+243000000000');

    $creator->delete();
    expect($user->fresh()->created_by)->toBeNull();

    $subscription->delete();
    expect($user->fresh()->subscription_id)->toBeNull();
    expect($user->fresh()->fleet_id)->toBeNull();
    $this->assertDatabaseMissing('fleets', ['id' => $fleet->id]);
    $this->assertDatabaseCount('fleet_user', 0);
});

test('authentication secrets are not exposed by user serialization', function () {
    $user = User::factory()->create();
    $user->forceFill(['two_factor_secret' => 'encrypted-secret', 'two_factor_recovery_codes' => 'encrypted-codes'])->save();

    expect($user->fresh()->toArray())->not->toHaveKeys(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes']);
});
