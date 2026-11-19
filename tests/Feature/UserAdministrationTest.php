<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_administrator_can_be_provisioned_without_creating_a_super_admin(): void
    {
        $password = Str::random(40);

        $this->artisan('app:provision-admin')
            ->expectsQuestion('Nom complet du premier administrateur', 'Administrateur de test')
            ->expectsQuestion('Adresse e-mail professionnelle', 'first-admin@example.test')
            ->expectsQuestion('Mot de passe initial (12 caractères minimum)', $password)
            ->expectsQuestion('Confirmer le mot de passe initial', $password)
            ->assertExitCode(0);

        $administrator = User::query()->where('email', 'first-admin@example.test')->firstOrFail();

        $this->assertSame(UserRole::Administrator, $administrator->role);
        $this->assertTrue($administrator->is_active);
        $this->assertTrue($administrator->must_change_password);
        $this->assertTrue(Hash::check($password, $administrator->password));
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseMissing('users', ['role' => UserRole::SuperAdmin->value]);

        $this->post(route('login'), [
            'email' => $administrator->email,
            'password' => $password,
        ])->assertRedirect(route('password.change'));

        $this->assertAuthenticatedAs($administrator);
        $this->get(route('users.index'))->assertRedirect(route('password.change'));
    }

    public function test_first_administrator_command_refuses_to_replace_an_existing_administrator(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        $originalHash = $administrator->password;

        $this->artisan('app:provision-admin')->assertExitCode(1);

        $this->assertSame($originalHash, $administrator->fresh()->password);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_first_administrator_command_rejects_short_or_unconfirmed_passwords(): void
    {
        $shortPassword = Str::random(8);
        $validPassword = Str::random(40);

        $this->artisan('app:provision-admin')
            ->expectsQuestion('Nom complet du premier administrateur', 'Administrateur de test')
            ->expectsQuestion('Adresse e-mail professionnelle', 'short-password@example.test')
            ->expectsQuestion('Mot de passe initial (12 caractères minimum)', $shortPassword)
            ->expectsQuestion('Confirmer le mot de passe initial', $shortPassword)
            ->assertExitCode(1);

        $this->artisan('app:provision-admin')
            ->expectsQuestion('Nom complet du premier administrateur', 'Administrateur de test')
            ->expectsQuestion('Adresse e-mail professionnelle', 'mismatch@example.test')
            ->expectsQuestion('Mot de passe initial (12 caractères minimum)', $validPassword)
            ->expectsQuestion('Confirmer le mot de passe initial', Str::random(40))
            ->assertExitCode(1);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_provision_command_creates_only_the_requested_super_admin_and_forces_password_change(): void
    {
        $password = Str::random(40);

        $this->artisan('app:provision-super-admin')
            ->expectsQuestion('Mot de passe initial (12 caractères minimum)', $password)
            ->expectsQuestion('Confirmer le mot de passe initial', $password)
            ->assertExitCode(0);

        $user = User::query()->where('email', 'mobelezyedenmaitreya@gmail.com')->firstOrFail();

        $this->assertSame(UserRole::SuperAdmin, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->must_change_password);
        $this->assertTrue(Hash::check($password, $user->password));
    }

    public function test_provision_command_does_not_overwrite_an_existing_email(): void
    {
        $existing = User::factory()->create([
            'email' => 'mobelezyedenmaitreya@gmail.com',
            'role' => UserRole::Viewer,
        ]);
        $originalHash = $existing->password;

        $this->artisan('app:provision-super-admin')->assertExitCode(1);

        $this->assertSame($originalHash, $existing->fresh()->password);
        $this->assertSame(UserRole::Viewer, $existing->fresh()->role);
    }

    public function test_first_login_requires_a_new_password_before_other_pages(): void
    {
        $currentPassword = Str::random(40);
        $newPassword = Str::random(40);
        $user = User::factory()->create([
            'password' => $currentPassword,
            'must_change_password' => true,
        ]);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => $currentPassword,
        ])->assertRedirect(route('password.change'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->get(route('password.change'))->assertOk();
        $this->get(route('assets.index'))->assertRedirect(route('password.change'));

        $this->put(route('password.update'), [
            'current_password' => $currentPassword,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])->assertRedirect(route('dashboard'));

        $this->assertFalse($user->fresh()->must_change_password);
        $this->assertTrue(Hash::check($newPassword, $user->fresh()->password));
    }

    public function test_super_admin_can_create_update_and_reset_user_passwords(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::SuperAdmin]));
        $initialPassword = Str::random(40);
        $resetPassword = Str::random(40);

        $this->get(route('users.create'))->assertOk();

        $this->post(route('users.store'), [
            'name' => 'Gestionnaire de test',
            'email' => 'manager@example.test',
            'role' => UserRole::AssetManager->value,
            'is_active' => '1',
            'password' => $initialPassword,
            'password_confirmation' => $initialPassword,
        ])->assertRedirect(route('users.index'));

        $managedUser = User::query()->where('email', 'manager@example.test')->firstOrFail();
        $this->get(route('users.show', $managedUser))
            ->assertOk()
            ->assertSee($managedUser->email)
            ->assertSee('Gestionnaire du patrimoine');
        $this->get(route('users.edit', $managedUser))->assertOk();
        $this->assertTrue($managedUser->must_change_password);

        $this->put(route('users.update', $managedUser), [
            'name' => 'Gestionnaire modifié',
            'email' => $managedUser->email,
            'role' => UserRole::Viewer->value,
            'is_active' => '0',
        ])->assertRedirect(route('users.index'));

        $this->put(route('users.password', $managedUser), [
            'password' => $resetPassword,
            'password_confirmation' => $resetPassword,
        ])->assertRedirect(route('users.edit', $managedUser));

        $managedUser->refresh();
        $this->assertSame(UserRole::Viewer, $managedUser->role);
        $this->assertFalse($managedUser->is_active);
        $this->assertTrue($managedUser->must_change_password);
        $this->assertTrue(Hash::check($resetPassword, $managedUser->password));
    }

    public function test_permissions_distinguish_viewer_asset_manager_and_administrator(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $this->actingAs($viewer);
        $this->get(route('assets.index'))->assertOk();
        $this->get(route('assignments.index'))->assertOk();
        $this->get(route('assets.create'))->assertForbidden();
        $this->get(route('assignments.create'))->assertForbidden();
        $this->get(route('users.index'))->assertForbidden();

        $manager = User::factory()->create(['role' => UserRole::AssetManager]);
        $this->actingAs($manager);
        $this->get(route('assets.create'))->assertOk();
        $this->get(route('users.index'))->assertForbidden();

        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        $protectedSuperAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($administrator);
        $this->get(route('users.index'))
            ->assertOk()
            ->assertDontSee($protectedSuperAdmin->email);
        $this->get(route('users.edit', $protectedSuperAdmin))->assertForbidden();
        $this->get(route('users.show', $protectedSuperAdmin))->assertForbidden();
        $password = Str::random(40);
        $this->post(route('users.store'), [
            'name' => 'Tentative de promotion',
            'email' => 'promotion@example.test',
            'role' => UserRole::SuperAdmin->value,
            'is_active' => '1',
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertSessionHasErrors('role');
    }

    public function test_last_super_admin_cannot_disable_or_demote_itself(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($superAdmin);

        $this->put(route('users.update', $superAdmin), [
            'name' => $superAdmin->name,
            'email' => $superAdmin->email,
            'role' => UserRole::SuperAdmin->value,
            'is_active' => '0',
        ])->assertSessionHasErrors('is_active');

        $this->put(route('users.update', $superAdmin), [
            'name' => $superAdmin->name,
            'email' => $superAdmin->email,
            'role' => UserRole::Viewer->value,
            'is_active' => '1',
        ])->assertSessionHasErrors('role');

        $this->assertSame(UserRole::SuperAdmin, $superAdmin->fresh()->role);
        $this->assertTrue($superAdmin->fresh()->is_active);
    }

    public function test_administrator_cannot_change_its_own_role_or_disable_its_account(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        $this->actingAs($administrator);

        $this->put(route('users.update', $administrator), [
            'name' => $administrator->name,
            'email' => $administrator->email,
            'role' => UserRole::AssetManager->value,
            'is_active' => '1',
        ])->assertSessionHasErrors('role');

        $this->put(route('users.update', $administrator), [
            'name' => $administrator->name,
            'email' => $administrator->email,
            'role' => UserRole::Administrator->value,
            'is_active' => '0',
        ])->assertSessionHasErrors('is_active');

        $this->assertSame(UserRole::Administrator, $administrator->fresh()->role);
        $this->assertTrue($administrator->fresh()->is_active);
    }

    public function test_inactive_accounts_cannot_sign_in(): void
    {
        $password = Str::random(40);
        $user = User::factory()->create([
            'is_active' => false,
            'password' => $password,
        ]);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => $password,
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
