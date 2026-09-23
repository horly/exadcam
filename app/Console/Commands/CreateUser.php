<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\Fleet;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CreateUser extends Command
{
    protected $signature = 'app:create-user {--name= : Nom du compte} {--email= : Adresse e-mail du compte} {--role=user : Rôle du compte (user, admin, superadmin)} {--fleet= : Identifiant de la flotte active (obligatoire pour admin et user)}';

    protected $description = 'Créer un compte EXADCAM avec saisie masquée du mot de passe';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Exécutez cette commande dans un terminal interactif pour saisir le mot de passe de manière masquée.');

            return self::FAILURE;
        }

        $identity = [
            'role' => $this->option('role'),
            'name' => trim($this->option('name') ?? $this->ask('Nom du compte') ?? ''),
            'email' => Str::lower(trim($this->option('email') ?? $this->ask('Adresse e-mail') ?? '')),
        ];

        $validator = Validator::make($identity, [
            'role' => ['required', Rule::enum(UserRole::class)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:254', 'unique:users,email'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $fleetId = $this->option('fleet');
        if ($identity['role'] === UserRole::Superadmin->value) {
            if ($fleetId !== null) {
                $this->error('Un superadmin accède à toutes les flottes ; ne renseignez pas --fleet.');

                return self::FAILURE;
            }
        } elseif (Validator::make(['fleet_id' => $fleetId], [
            'fleet_id' => ['required', 'integer', Rule::exists('fleets', 'id')->where('status', 'active')],
        ])->fails()) {
            $this->error('Indiquez une flotte active avec --fleet pour créer un admin ou un utilisateur.');

            return self::FAILURE;
        }

        $credentials = [
            'password' => $this->secret('Mot de passe (12 caractères minimum)', false),
            'password_confirmation' => $this->secret('Confirmez le mot de passe', false),
        ];

        $validator = Validator::make($credentials, [
            'password' => ['required', 'string', 'min:12', 'confirmed', function ($attribute, $value, $fail) {
                if (strlen($value) > 72) {
                    $fail('Le mot de passe ne doit pas dépasser 72 octets.');
                }
            }],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $created = DB::transaction(function () use ($identity, $credentials, $fleetId): bool {
            $fleet = $fleetId !== null ? Fleet::query()->lockForUpdate()->find($fleetId) : null;
            // Recheck after password entry: the fleet may have changed in the meantime.
            if ($identity['role'] !== UserRole::Superadmin->value && (! $fleet || $fleet->status !== 'active')) {
                return false;
            }
            $user = User::create([...$identity, 'fleet_id' => $fleet?->id,
                'password' => $credentials['password'], 'status' => 'active']);
            if ($fleet) {
                $user->fleets()->sync([$fleet->id => [
                    'permission' => $user->isAdmin() ? 'manager' : 'viewer',
                ]]);
            }

            return true;
        });
        if (! $created) {
            $this->error('La flotte sélectionnée n’est plus active. Aucun compte créé.');

            return self::FAILURE;
        }

        $this->info('Compte EXADCAM créé. Vous pouvez maintenant vous connecter.');

        return self::SUCCESS;
    }
}
