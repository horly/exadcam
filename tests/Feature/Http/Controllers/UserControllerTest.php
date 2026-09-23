<?php

use App\Enums\UserRole;
use App\Models\Fleet;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(LazilyRefreshDatabase::class);

function managedFleet(string $code = 'FLEET-A', string $status = 'active'): Fleet
{
    return Fleet::create(['name' => 'Flotte '.$code, 'code' => $code, 'status' => $status]);
}

function managedUserPayload(Fleet $fleet, array $overrides = []): array
{
    return array_replace([
        'name' => 'Agent EXADCAM', 'email' => 'agent@example.test', 'role' => 'user',
        'fleet_id' => $fleet->id, 'password' => 'Test-Only-Secret-2026!',
        'password_confirmation' => 'Test-Only-Secret-2026!', 'phone' => '+243000000000',
        'address' => 'Adresse de test', 'permissions' => ['map.view'],
    ], $overrides);
}

test('guests receive 401 on the users data and mutation endpoints', function (string $method, string $path) {
    $user = User::factory()->create();
    $this->json($method, str_replace('{id}', (string) $user->id, $path))->assertUnauthorized();
})->with([
    ['GET', '/users'], ['GET', '/users/options'], ['POST', '/users'],
    ['PUT', '/users/{id}'], ['DELETE', '/users/{id}'], ['GET', '/users/{id}/login-history'],
]);

test('simple users cannot list create update delete or read managed users histories', function () {
    $actor = User::factory()->create();
    $target = User::factory()->create();
    $this->actingAs($actor)->getJson('/users')->assertForbidden();
    $this->getJson('/users/options')->assertForbidden();
    $this->postJson('/users')->assertForbidden();
    $this->putJson('/users/'.$target->id)->assertNotFound();
    $this->deleteJson('/users/'.$target->id)->assertNotFound();
    $this->getJson('/users/'.$target->id.'/login-history')->assertNotFound();
    $this->get('/')->assertDontSee('id="users-module"', false);
});

test('inactive administrators cannot retrieve users data', function () {
    $actor = User::factory()->create(['role' => UserRole::Admin, 'status' => 'disabled']);
    $this->actingAs($actor)->getJson('/users')->assertRedirectToRoute('login');
    $this->assertGuest();
});

test('superadmin sees protected account first with paginated searchable sortable users and no secrets', function () {
    $actor = User::factory()->create(['role' => UserRole::Superadmin, 'name' => 'Root']);
    $fleet = managedFleet();
    foreach (range(1, 6) as $number) {
        User::factory()->create(['fleet_id' => $fleet->id, 'name' => 'Agent '.$number, 'email' => "agent{$number}@example.test"]);
    }
    $response = $this->actingAs($actor)->getJson('/users')->assertOk()
        ->assertJsonPath('meta.total', 7)->assertJsonCount(5, 'records')
        ->assertJsonPath('records.0.id', $actor->id)->assertJsonPath('stats.active', 7);
    expect($response->json('html'))->toContain('Compte superadmin protégé')->not->toContain('data-user-edit="'.$actor->id.'"');
    expect(array_keys($response->json('records.0')))->toBe(['id', 'name', 'email', 'role', 'fleet_id', 'phone', 'address', 'permissions']);
    $this->getJson('/users?search=agent4')->assertJsonCount(1, 'records')->assertJsonPath('records.0.name', 'Agent 4');
    $this->getJson('/users?sort=name&direction=asc')->assertJsonPath('records.1.name', 'Agent 1');
    $this->getJson('/users?page=2')->assertJsonCount(2, 'records');
    $this->getJson('/users?per_page=25')->assertJsonCount(7, 'records');
    $this->get('/users')->assertRedirect(route('dashboard').'#users');
});

test('a fleet admin only lists simple users and active fleet choices from its own fleet', function () {
    $fleet = managedFleet();
    $otherFleet = managedFleet('OTHER');
    $actor = User::factory()->create(['role' => UserRole::Admin, 'fleet_id' => $fleet->id]);
    $user = User::factory()->create(['fleet_id' => $fleet->id]);
    User::factory()->create(['role' => UserRole::Admin, 'fleet_id' => $fleet->id]);
    User::factory()->create(['fleet_id' => $otherFleet->id]);
    $this->actingAs($actor)->getJson('/users')->assertOk()->assertJsonCount(1, 'records')
        ->assertJsonPath('records.0.id', $user->id)->assertJsonPath('stats.total', 1);
    $this->getJson('/users/options')->assertJsonCount(1, 'fleets')->assertJsonPath('fleets.0.id', $fleet->id);
});

test('an admin without a fleet cannot see or adopt unassigned accounts', function () {
    $actor = User::factory()->create(['role' => UserRole::Admin]);
    $target = User::factory()->create();
    $this->actingAs($actor)->getJson('/users')->assertJsonCount(0, 'records');
    $this->getJson('/users/options')->assertJsonCount(0, 'fleets');
    $this->putJson('/users/'.$target->id)->assertNotFound();
    $this->postJson('/users', ['name' => 'Test', 'email' => 'test@example.test', 'password' => 'Strong-Testing12!', 'password_confirmation' => 'Strong-Testing12!'])
        ->assertUnprocessable()->assertJsonValidationErrors(['fleet_id']);
});

test('superadmin creates an active fleet user with a hashed password and server owned fields', function () {
    $actor = User::factory()->create(['role' => UserRole::Superadmin]);
    $fleet = managedFleet();
    $data = managedUserPayload($fleet, ['email' => ' AGENT@EXAMPLE.TEST ', 'created_by' => 999,
        'status' => 'disabled', 'disabled_at' => '2020-01-01', 'remember_token' => 'attacker', 'subscription_id' => 999]);
    $this->actingAs($actor)->postJson('/users', $data)->assertCreated()->assertJsonPath('message', 'Utilisateur créé avec succès.');
    $user = User::where('email', 'agent@example.test')->firstOrFail();
    expect($user->created_by)->toBe($actor->id)->and($user->isActive())->toBeTrue()
        ->and($user->permissions)->toBe(['map.view'])->and($user->subscription_id)->toBeNull()
        ->and($user->remember_token)->toBeNull()->and(Hash::check($data['password'], $user->password))->toBeTrue();
    $this->assertDatabaseHas('fleet_user', ['user_id' => $user->id, 'fleet_id' => $fleet->id, 'permission' => 'viewer']);
});

test('fleet admin creates only a user in its own fleet even when role and fleet are omitted', function () {
    $fleet = managedFleet();
    $actor = User::factory()->create(['role' => UserRole::Admin, 'fleet_id' => $fleet->id]);
    $data = managedUserPayload($fleet);
    unset($data['role'], $data['fleet_id']);
    $this->actingAs($actor)->postJson('/users', $data)->assertCreated();
    $this->assertDatabaseHas('users', ['email' => $data['email'], 'role' => 'user', 'fleet_id' => $fleet->id]);
});

test('admin cannot create a manager or assign an account to another fleet', function (string $field) {
    $fleet = managedFleet();
    $other = managedFleet('OTHER');
    $actor = User::factory()->create(['role' => UserRole::Admin, 'fleet_id' => $fleet->id]);
    $data = managedUserPayload($fleet, [$field => $field === 'role' ? 'admin' : $other->id]);
    $this->actingAs($actor)->postJson('/users', $data)->assertUnprocessable()->assertJsonValidationErrors([$field]);
    $this->assertDatabaseMissing('users', ['email' => $data['email']]);
})->with(['role', 'fleet_id']);

test('superadmin can move a user to another fleet promote it and retain an unchanged password', function () {
    $old = managedFleet();
    $new = managedFleet('NEW');
    $actor = User::factory()->create(['role' => UserRole::Superadmin]);
    $user = User::factory()->create(['fleet_id' => $old->id, 'created_by' => $actor->id, 'permissions' => ['map.view']]);
    $user->fleets()->attach($old, ['permission' => 'viewer']);
    $hash = $user->password;
    $this->actingAs($actor)->putJson('/users/'.$user->id, managedUserPayload($new, [
        'role' => 'admin', 'password' => '', 'password_confirmation' => '', 'created_by' => 999,
    ]))->assertOk()->assertJsonPath('message', 'Utilisateur modifié avec succès.');
    $user->refresh();
    expect($user->isAdmin())->toBeTrue()->and($user->permissions)->toBe([])->and($user->password)->toBe($hash)
        ->and($user->created_by)->toBe($actor->id)->and($user->email_verified_at)->toBeNull();
    $this->assertDatabaseMissing('fleet_user', ['user_id' => $user->id, 'fleet_id' => $old->id]);
    $this->assertDatabaseHas('fleet_user', ['user_id' => $user->id, 'fleet_id' => $new->id, 'permission' => 'manager']);
});

test('password changes revoke remembered access and database sessions of the target only', function () {
    config(['session.driver' => 'database']);
    $actor = User::factory()->create(['role' => UserRole::Superadmin]);
    $fleet = managedFleet();
    $target = User::factory()->create(['fleet_id' => $fleet->id, 'remember_token' => 'remembered-before']);
    $other = User::factory()->create();
    foreach ([$target, $other] as $user) {
        DB::table('sessions')->insert(['id' => 'session-'.$user->id, 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
    }
    $data = managedUserPayload($fleet, ['email' => $target->email]);
    $this->actingAs($actor)->putJson('/users/'.$target->id, $data)->assertOk();
    expect(Hash::check($data['password'], $target->fresh()->password))->toBeTrue()
        ->and($target->fresh()->remember_token)->not->toBe('remembered-before');
    $this->assertDatabaseMissing('sessions', ['user_id' => $target->id]);
    $this->assertDatabaseHas('sessions', ['user_id' => $other->id]);
});

test('cross fleet records cannot be changed deleted or inspected by an admin', function () {
    $fleet = managedFleet();
    $other = managedFleet('OTHER');
    $actor = User::factory()->create(['role' => UserRole::Admin, 'fleet_id' => $fleet->id]);
    $target = User::factory()->create(['fleet_id' => $other->id]);
    $this->actingAs($actor)->putJson('/users/'.$target->id, managedUserPayload($fleet))->assertNotFound();
    $this->deleteJson('/users/'.$target->id)->assertNotFound();
    $this->getJson('/users/'.$target->id.'/login-history')->assertNotFound();
    expect($target->fresh()->fleet_id)->toBe($other->id);
});

test('a superadmin account cannot be created edited or deleted through management', function () {
    $actor = User::factory()->create(['role' => UserRole::Superadmin]);
    $fleet = managedFleet();
    $this->actingAs($actor)->postJson('/users', managedUserPayload($fleet, ['role' => 'superadmin']))
        ->assertUnprocessable()->assertJsonValidationErrors(['role']);
    $this->putJson('/users/'.$actor->id, managedUserPayload($fleet))->assertForbidden();
    $this->deleteJson('/users/'.$actor->id)->assertForbidden();
    expect($actor->fresh()->isSuperadmin())->toBeTrue();
});

test('user deletion removes the account fleet relation and history without deleting the fleet', function () {
    $fleet = managedFleet();
    $actor = User::factory()->create(['role' => UserRole::Admin, 'fleet_id' => $fleet->id]);
    $user = User::factory()->create(['fleet_id' => $fleet->id]);
    $user->fleets()->attach($fleet, ['permission' => 'viewer']);
    $user->loginHistories()->create(['device' => 'Device test', 'logged_in_at' => '2026-09-16 10:00:00']);
    $this->actingAs($actor)->deleteJson('/users/'.$user->id)->assertOk()->assertJsonPath('message', 'Utilisateur supprimé avec succès.');
    $this->assertDatabaseMissing('users', ['id' => $user->id]);
    $this->assertDatabaseMissing('fleet_user', ['user_id' => $user->id]);
    $this->assertDatabaseMissing('user_login_histories', ['user_id' => $user->id]);
    $this->assertDatabaseHas('fleets', ['id' => $fleet->id]);
});

test('required identity credentials role and fleet fields return validation errors', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Superadmin]))->postJson('/users', [])
        ->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'password', 'role', 'fleet_id'])
        ->assertJsonPath('errors.name.0', 'Le champ Nom est obligatoire.');
});

test('invalid account fields are rejected without inserting any account', function (array $invalid, string $error) {
    $fleet = managedFleet();
    $actor = User::factory()->create(['role' => UserRole::Superadmin]);
    $this->actingAs($actor)->postJson('/users', managedUserPayload($fleet, $invalid))
        ->assertUnprocessable()->assertJsonValidationErrors([$error]);
    $this->assertDatabaseCount('users', 1);
})->with([
    'long name' => [['name' => str_repeat('a', 256)], 'name'],
    'email format' => [['email' => 'not-an-email'], 'email'],
    'confirmation' => [['password_confirmation' => 'different'], 'password'],
    'weak password' => [['password' => 'weak', 'password_confirmation' => 'weak'], 'password'],
    'bcrypt bytes' => [['password' => str_repeat('é', 36).'Az1!', 'password_confirmation' => str_repeat('é', 36).'Az1!'], 'password'],
    'unknown permission' => [['permissions' => ['platform.all']], 'permissions.0'],
    'permission scalar' => [['permissions' => 'map.view'], 'permissions'],
    'long phone' => [['phone' => str_repeat('1', 41)], 'phone'],
    'long address' => [['address' => str_repeat('a', 256)], 'address'],
    'unknown fleet' => [['fleet_id' => 999999], 'fleet_id'],
]);

test('duplicate email and inactive fleets are rejected on save', function () {
    $actor = User::factory()->create(['role' => UserRole::Superadmin]);
    $fleet = managedFleet('OFF', 'disabled');
    $this->actingAs($actor)->postJson('/users', managedUserPayload($fleet, ['email' => $actor->email]))
        ->assertUnprocessable()->assertJsonValidationErrors(['email', 'fleet_id']);
    $this->getJson('/users/options')->assertJsonCount(0, 'fleets');
});

test('untrusted names email phone and fleet labels are escaped in the table', function () {
    $actor = User::factory()->create(['role' => UserRole::Superadmin]);
    $fleet = managedFleet();
    $fleet->update(['name' => '<img src=x onerror=alert(1)>']);
    User::factory()->create(['fleet_id' => $fleet->id, 'name' => '<script>alert(1)</script>', 'email' => '<b>email</b>', 'phone' => '<b>phone</b>']);
    $response = $this->actingAs($actor)->getJson('/users');
    expect($response->json('html'))->toContain('&lt;script&gt;', '&lt;img', '&lt;b&gt;email', '&lt;b&gt;phone')
        ->not->toContain('<script>alert(1)</script>', '<img src=x', '<b>email</b>', '<b>phone</b>');
});

test('query identifiers and pagination are validated before building the list', function (string $query, string $field) {
    $actor = User::factory()->create(['role' => UserRole::Superadmin]);
    $this->actingAs($actor)->getJson('/users?'.$query)->assertUnprocessable()->assertJsonValidationErrors([$field]);
})->with([
    ['sort=name%20desc%3Bdrop%20table%20users', 'sort'], ['direction=anything', 'direction'],
    ['page=-1', 'page'], ['per_page=9999', 'per_page'], ['search[]=x', 'search'],
]);

test('user screens are translated in English', function () {
    $actor = User::factory()->create(['role' => UserRole::Superadmin]);
    $this->actingAs($actor)->withSession(['locale' => 'en'])->get('/')->assertOk()
        ->assertSee('User directory')->assertSee('New user')->assertSee('Account security');
    $this->getJson('/users')->assertOk()->assertSee('Protected superadmin account');
});

test('an admin edits own fleet user permissions but cannot manage another administrator', function () {
    $fleet = managedFleet();
    $actor = User::factory()->create(['role' => UserRole::Admin, 'fleet_id' => $fleet->id]);
    $user = User::factory()->create(['fleet_id' => $fleet->id, 'permissions' => ['map.view']]);
    $data = managedUserPayload($fleet, ['email' => $user->email, 'permissions' => [], 'password' => '', 'password_confirmation' => '']);
    $this->actingAs($actor)->putJson('/users/'.$user->id, $data)->assertOk();
    expect($user->fresh()->permissions)->toBe([]);
    $this->getJson('/users/'.$user->id.'/login-history')->assertOk();
    $this->putJson('/users/'.$actor->id, $data)->assertNotFound();
    $this->deleteJson('/users/'.$actor->id)->assertNotFound();
});

test('creating an administrator stores manager membership without individual permissions', function () {
    $fleet = managedFleet();
    $actor = User::factory()->create(['role' => UserRole::Superadmin]);
    $this->actingAs($actor)->postJson('/users', managedUserPayload($fleet, ['role' => 'admin']))->assertCreated();
    $created = User::where('email', 'agent@example.test')->firstOrFail();
    expect($created->isAdmin())->toBeTrue()->and($created->permissions)->toBe([]);
    $this->assertDatabaseHas('fleet_user', ['user_id' => $created->id, 'fleet_id' => $fleet->id, 'permission' => 'manager']);
});

test('user writes require csrf tokens without changing database records', function () {
    $actor = User::factory()->create(['role' => UserRole::Superadmin]);
    $target = User::factory()->create();
    $this->app->detectEnvironment(fn () => 'local');
    $this->actingAs($actor)->postJson('/users', [])->assertStatus(419);
    $this->putJson('/users/'.$target->id, [])->assertStatus(419);
    $this->deleteJson('/users/'.$target->id)->assertStatus(419);
    $this->assertDatabaseCount('users', 2);
});
