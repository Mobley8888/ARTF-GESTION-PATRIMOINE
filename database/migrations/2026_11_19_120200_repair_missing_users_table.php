<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            $requiredColumns = [
                'id',
                'name',
                'email',
                'email_verified_at',
                'password',
                'remember_token',
                'role',
                'is_active',
                'must_change_password',
                'last_login_at',
                'created_at',
                'updated_at',
            ];
            $missingColumns = array_filter(
                $requiredColumns,
                fn (string $column): bool => ! Schema::hasColumn('users', $column),
            );

            if ($missingColumns !== []) {
                throw new RuntimeException(
                    'La table users existe déjà mais son schéma est incomplet; aucune modification automatique n’a été effectuée.',
                );
            }

            return;
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
            $table->string('role', 32)->default(UserRole::Viewer->value)->index();
            $table->boolean('is_active')->default(true);
            $table->boolean('must_change_password')->default(false);
            $table->timestamp('last_login_at')->nullable();
        });
    }

    public function down(): void
    {
        throw new LogicException(
            'Cette migration corrective ne peut pas être annulée automatiquement, car elle pourrait supprimer des comptes et des données d’audit.',
        );
    }
};
