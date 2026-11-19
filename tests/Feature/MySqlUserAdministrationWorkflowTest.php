<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class MySqlUserAdministrationWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Ce scénario doit être exécuté sur MySQL.');
        }
    }

    public function test_mysql_administrator_can_manage_accounts_without_self_elevation(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        $initialPassword = Str::random(48);
        $resetPassword = Str::random(48);
        $email = 'managed-'.Str::lower(Str::random(16)).'@example.test';
        $this->actingAs($administrator);

        $this->get(route('users.index'))->assertOk();
        $this->post(route('users.store'), [
            'name' => 'Compte géré MySQL',
            'email' => $email,
            'role' => UserRole::Viewer->value,
            'is_active' => '1',
            'password' => $initialPassword,
            'password_confirmation' => $initialPassword,
        ])->assertRedirect(route('users.index'));

        $managedUser = User::query()->where('email', $email)->firstOrFail();
        $this->assertTrue($managedUser->must_change_password);
        $this->assertTrue(Hash::check($initialPassword, $managedUser->password));
        $this->get(route('users.index', ['q' => $email, 'role' => UserRole::Viewer->value, 'active' => 'active']))
            ->assertOk()
            ->assertSee($email);
        $this->get(route('users.show', $managedUser))
            ->assertOk()
            ->assertSee($email)
            ->assertSee('Agent de consultation');

        $this->put(route('users.update', $managedUser), [
            'name' => 'Compte MySQL modifié',
            'email' => $email,
            'role' => UserRole::AssetManager->value,
            'is_active' => '1',
        ])->assertRedirect(route('users.index'));

        $managedUser->refresh();
        $this->assertSame('Compte MySQL modifié', $managedUser->name);
        $this->assertSame(UserRole::AssetManager, $managedUser->role);
        $this->get(route('users.show', $managedUser))
            ->assertOk()
            ->assertSee('Gestionnaire du patrimoine');

        $this->put(route('users.password', $managedUser), [
            'password' => $resetPassword,
            'password_confirmation' => $resetPassword,
        ])->assertRedirect(route('users.edit', $managedUser));
        $this->assertTrue(Hash::check($resetPassword, $managedUser->fresh()->password));
        $this->assertTrue($managedUser->fresh()->must_change_password);

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

        $this->put(route('users.update', $managedUser), [
            'name' => $managedUser->name,
            'email' => $managedUser->email,
            'role' => UserRole::Viewer->value,
            'is_active' => '0',
        ])->assertRedirect(route('users.index'));
        $managedUser->refresh();
        $this->assertFalse($managedUser->is_active);

        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $this->actingAs($viewer);
        $this->get(route('users.index'))->assertForbidden();
        $this->get(route('users.show', $managedUser))->assertForbidden();
        $this->post(route('users.store'))->assertForbidden();
        $this->put(route('users.update', $managedUser))->assertForbidden();
        $this->put(route('users.password', $managedUser))->assertForbidden();

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->post(route('login'), [
            'email' => $managedUser->email,
            'password' => $resetPassword,
        ])->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
