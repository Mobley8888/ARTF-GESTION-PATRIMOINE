<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProvisionAdministratorCommand extends Command
{
    protected $signature = 'app:provision-admin';

    protected $description = 'Provisionne le premier compte administrateur avec saisie masquée du mot de passe.';

    public function handle(): int
    {
        if ($this->hasAdministrativeAccount()) {
            $this->error('Un compte administrateur existe déjà; aucun compte n’a été créé.');

            return self::FAILURE;
        }

        $name = $this->ask('Nom complet du premier administrateur');
        $email = $this->ask('Adresse e-mail professionnelle');
        $email = is_string($email) ? mb_strtolower(trim($email)) : '';

        $validator = Validator::make(
            [
                'name' => is_string($name) ? trim($name) : '',
                'email' => $email,
            ],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            ],
            [
                'name.required' => 'Le nom complet est obligatoire.',
                'email.required' => 'L’adresse e-mail est obligatoire.',
                'email.email' => 'Saisissez une adresse e-mail valide.',
                'email.unique' => 'Cette adresse e-mail est déjà utilisée.',
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $data = $validator->validated();
        $password = $this->secret('Mot de passe initial (12 caractères minimum)');
        $confirmation = $this->secret('Confirmer le mot de passe initial');

        if (! is_string($password) || mb_strlen($password) < 12) {
            $this->error('Le mot de passe doit comporter au moins 12 caractères.');

            return self::FAILURE;
        }

        if (! is_string($confirmation) || ! hash_equals($password, $confirmation)) {
            $this->error('Les deux saisies ne correspondent pas.');

            return self::FAILURE;
        }

        $result = DB::transaction(function () use ($data, $password): string {
            User::query()->orderBy('id')->lockForUpdate()->get(['id']);

            if ($this->hasAdministrativeAccount()) {
                return 'account_exists';
            }

            if (User::query()->where('email', $data['email'])->exists()) {
                return 'email_exists';
            }

            User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $password,
                'role' => UserRole::Administrator,
                'is_active' => true,
                'must_change_password' => true,
            ]);

            return 'created';
        }, attempts: 3);

        if ($result === 'account_exists') {
            $this->error('Un compte administrateur existe déjà; aucun compte n’a été créé.');

            return self::FAILURE;
        }

        if ($result === 'email_exists') {
            $this->error('Cette adresse e-mail est déjà utilisée.');

            return self::FAILURE;
        }

        $this->info('Le compte administrateur a été créé. Le changement de mot de passe sera obligatoire à la première connexion.');

        return self::SUCCESS;
    }

    private function hasAdministrativeAccount(): bool
    {
        return User::query()
            ->whereIn('role', [UserRole::Administrator->value, UserRole::SuperAdmin->value])
            ->exists();
    }
}
