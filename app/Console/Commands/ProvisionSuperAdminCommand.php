<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class ProvisionSuperAdminCommand extends Command
{
    protected $signature = 'app:provision-super-admin';

    protected $description = 'Provisionne le compte super administrateur avec un mot de passe saisi de façon masquée.';

    public function handle(): int
    {
        $email = 'mobelezyedenmaitreya@gmail.com';

        if (User::query()->where('email', $email)->exists()) {
            $this->error('Un compte existe déjà pour cette adresse; aucune modification n’a été effectuée.');

            return self::FAILURE;
        }

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

        User::query()->create([
            'name' => 'Super administrateur',
            'email' => $email,
            'password' => Hash::make($password),
            'role' => UserRole::SuperAdmin,
            'is_active' => true,
            'must_change_password' => true,
        ]);

        $this->info('Le compte super administrateur a été créé. Le changement de mot de passe sera obligatoire à la première connexion.');

        return self::SUCCESS;
    }
}
