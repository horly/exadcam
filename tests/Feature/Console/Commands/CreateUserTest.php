<?php

use App\Models\Fleet;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->fleet = Fleet::create(['name' => 'Assigned fleet', 'code' => 'ASSIGNED', 'status' => 'active']);
});

test('an account is created with a normalized email and a hashed password', function () {
    $this->artisan('app:create-user', ['--name' => ' EXAD Operator ', '--email' => ' OPERATOR@EXAMPLE.TEST ', '--fleet' => $this->fleet->id])
        ->expectsQuestion('Mot de passe (12 caractères minimum)', 'A-test-secret-2026')
        ->expectsQuestion('Confirmez le mot de passe', 'A-test-secret-2026')
        ->expectsOutput('Compte EXADCAM créé. Vous pouvez maintenant vous connecter.')
        ->assertSuccessful();

    $this->assertDatabaseHas('users', ['name' => 'EXAD Operator', 'email' => 'operator@example.test']);
    $user = User::firstOrFail();
    expect(Hash::check('A-test-secret-2026', $user->password))->toBeTrue();
    expect($user->password)->not->toBe('A-test-secret-2026');
});

test('an existing account cannot be replaced by the provisioning command', function () {
    $user = User::factory()->create(['email' => 'operator@example.test']);

    $this->artisan('app:create-user', ['--name' => 'Replacement', '--email' => 'OPERATOR@EXAMPLE.TEST'])
        ->expectsOutput('Cette adresse e-mail est déjà utilisée.')
        ->assertFailed();

    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => $user->name, 'password' => $user->password]);
});

test('invalid identity details do not create accounts', function (string $name, string $email, string $message) {
    $this->artisan('app:create-user', ['--name' => $name, '--email' => $email])
        ->expectsOutput($message)
        ->assertFailed();

    $this->assertDatabaseCount('users', 0);
})->with([
    'missing name' => [' ', 'person@example.test', 'Le champ nom est obligatoire.'],
    'long name' => [str_repeat('a', 256), 'person@example.test', 'Le champ nom ne doit pas dépasser 255 caractères.'],
    'missing email' => ['Person', ' ', 'Le champ adresse e-mail est obligatoire.'],
    'invalid email' => ['Person', 'invalid', 'Saisissez une adresse e-mail valide.'],
]);

test('an invalid or unconfirmed password does not create an account', function (?string $password, ?string $confirmation, string $message) {
    $this->artisan('app:create-user', ['--name' => 'Person', '--email' => 'person@example.test', '--fleet' => $this->fleet->id])
        ->expectsQuestion('Mot de passe (12 caractères minimum)', $password)
        ->expectsQuestion('Confirmez le mot de passe', $confirmation)
        ->expectsOutput($message)
        ->assertFailed();

    $this->assertDatabaseCount('users', 0);
})->with([
    'empty' => [null, null, 'Le champ mot de passe est obligatoire.'],
    'too short' => ['short', 'short', 'Le champ mot de passe doit contenir au moins 12 caractères.'],
    'mismatched' => ['A-test-secret-2026', 'Something-else-2026', 'La confirmation du mot de passe ne correspond pas.'],
    'too many bytes' => [str_repeat('é', 37), str_repeat('é', 37), 'Le mot de passe ne doit pas dépasser 72 octets.'],
]);

test('non interactive provisioning is rejected without creating an account', function () {
    $this->artisan('app:create-user', ['--no-interaction' => true])
        ->expectsOutput('Exécutez cette commande dans un terminal interactif pour saisir le mot de passe de manière masquée.')
        ->assertFailed();

    $this->assertDatabaseCount('users', 0);
});

test('the provisioning command creates the explicitly selected role', function (string $role) {
    $this->artisan('app:create-user', ['--name' => 'Test operator', '--email' => 'operator@example.test', '--role' => $role, ...($role === 'superadmin' ? [] : ['--fleet' => $this->fleet->id])])
        ->expectsQuestion('Mot de passe (12 caractères minimum)', 'A-test-secret-2026')
        ->expectsQuestion('Confirmez le mot de passe', 'A-test-secret-2026')
        ->assertSuccessful();

    $this->assertDatabaseHas('users', ['email' => 'operator@example.test', 'role' => $role, 'status' => 'active',
        'fleet_id' => $role === 'superadmin' ? null : $this->fleet->id]);
    if ($role === 'superadmin') {
        $this->assertDatabaseCount('fleet_user', 0);
    } else {
        $this->assertDatabaseHas('fleet_user', ['user_id' => User::firstOrFail()->id, 'fleet_id' => $this->fleet->id,
            'permission' => $role === 'admin' ? 'manager' : 'viewer']);
    }
})->with(['user', 'admin', 'superadmin']);

test('the provisioning command rejects an unknown role', function () {
    $this->artisan('app:create-user', ['--name' => 'Test operator', '--email' => 'operator@example.test', '--role' => 'root'])
        ->expectsOutput('Le champ role sélectionné est invalide.')
        ->assertFailed();

    $this->assertDatabaseCount('users', 0);
});

test('client accounts cannot be provisioned without an active fleet', function (string $role, string $case) {
    $fleet = match ($case) {
        'missing' => null,
        'unknown' => 999999,
        'invalid' => 'not-a-fleet',
        'inactive' => Fleet::create(['name' => 'Inactive', 'code' => 'INACTIVE', 'status' => 'inactive'])->id,
    };
    $this->artisan('app:create-user', ['--name' => 'Unassigned', '--email' => 'unassigned@example.test',
        '--role' => $role, ...($fleet === null ? [] : ['--fleet' => $fleet])])
        ->expectsOutput('Indiquez une flotte active avec --fleet pour créer un admin ou un utilisateur.')
        ->assertFailed();
    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('fleet_user', 0);
})->with(['admin', 'user'])->with(['missing', 'unknown', 'invalid', 'inactive']);

test('a superadmin cannot be provisioned with a misleading single fleet assignment', function () {
    $this->artisan('app:create-user', ['--name' => 'Platform', '--email' => 'platform@example.test',
        '--role' => 'superadmin', '--fleet' => $this->fleet->id])
        ->expectsOutput('Un superadmin accède à toutes les flottes ; ne renseignez pas --fleet.')
        ->assertFailed();
    $this->assertDatabaseCount('users', 0);
});
