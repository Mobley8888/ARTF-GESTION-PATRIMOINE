<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Agent;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\Category;
use App\Models\Location;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Services\AssetAssignmentService;
use App\Services\AssetLocationService;
use Tests\TestCase;

class MySqlOperationalWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Ce scénario doit être exécuté sur MySQL.');
        }
    }

    public function test_mysql_super_admin_provision_login_password_change_and_logout(): void
    {
        $initialPassword = Str::random(48);
        $newPassword = Str::random(48);

        $this->artisan('app:provision-super-admin')
            ->expectsQuestion('Mot de passe initial (12 caractères minimum)', $initialPassword)
            ->expectsQuestion('Confirmer le mot de passe initial', $initialPassword)
            ->assertExitCode(0);

        $user = User::query()->where('email', 'mobelezyedenmaitreya@gmail.com')->firstOrFail();
        $this->assertSame(UserRole::SuperAdmin, $user->role);
        $this->assertTrue(Hash::check($initialPassword, $user->password));
        $originalHash = $user->password;
        $this->artisan('app:provision-super-admin')->assertExitCode(1);
        $this->assertSame($originalHash, $user->fresh()->password);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => $initialPassword,
        ])->assertRedirect(route('password.change'));

        $this->assertNotNull($user->fresh()->last_login_at);
        $this->get(route('assets.index'))->assertRedirect(route('password.change'));
        $this->put(route('password.update'), [
            'current_password' => $initialPassword,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])->assertRedirect(route('dashboard'));

        $this->assertFalse($user->fresh()->must_change_password);
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->post(route('login'), [
            'email' => $user->email,
            'password' => $newPassword,
        ])->assertRedirect(route('dashboard'));
        $this->post(route('logout'))->assertRedirect(route('login'));
    }

    public function test_mysql_user_management_roles_password_reset_and_access_control(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $temporaryPassword = Str::random(48);
        $resetPassword = Str::random(48);
        $this->actingAs($superAdmin);

        $this->post(route('users.store'), [
            'name' => 'MySQL managed account',
            'email' => 'mysql-managed@example.test',
            'role' => UserRole::AssetManager->value,
            'is_active' => '1',
            'password' => $temporaryPassword,
            'password_confirmation' => $temporaryPassword,
        ])->assertRedirect(route('users.index'));

        $managedUser = User::query()->where('email', 'mysql-managed@example.test')->firstOrFail();
        $this->assertTrue($managedUser->must_change_password);

        $this->put(route('users.update', $managedUser), [
            'name' => 'MySQL managed account',
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

        $managedUser->forceFill(['must_change_password' => false])->save();
        $this->actingAs($managedUser);
        $this->get(route('users.index'))->assertRedirect(route('login'));
        $this->get(route('assets.index'))->assertRedirect(route('login'));

        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        $this->actingAs($administrator);
        $this->get(route('users.index'))->assertOk();
        $this->get(route('users.edit', $superAdmin))->assertForbidden();
        $this->put(route('users.update', $superAdmin), [
            'name' => $superAdmin->name,
            'email' => $superAdmin->email,
            'role' => UserRole::Viewer->value,
            'is_active' => '1',
        ])->assertSessionHasErrors('role');

        $this->actingAs($superAdmin);
        $this->put(route('users.update', $superAdmin), [
            'name' => $superAdmin->name,
            'email' => $superAdmin->email,
            'role' => UserRole::SuperAdmin->value,
            'is_active' => '0',
        ])->assertSessionHasErrors('is_active');
    }

    public function test_mysql_assignment_transfer_return_history_and_active_uniqueness(): void
    {
        $actor = User::factory()->create(['role' => UserRole::AssetManager]);
        $asset = Asset::factory()->create();
        $agent = Agent::factory()->create();
        $secondAgent = Agent::factory()->create();
        $service = Service::factory()->create();
        $this->actingAs($actor);

        $assignedAt = now()->subMinutes(3)->format('Y-m-d\TH:i');
        $transferredAt = now()->subMinute()->format('Y-m-d\TH:i');

        $this->post(route('assignments.store'), [
            'asset_id' => $asset->getKey(),
            'agent_id' => $agent->getKey(),
            'service_id' => '',
            'assigned_at' => $assignedAt,
            'reason' => 'Affectation MySQL',
        ])->assertRedirect(route('assignments.show', $asset));
        $this->get(route('assignments.agent', $agent))
            ->assertOk()
            ->assertSee($asset->asset_code);

        $this->post(route('assignments.store'), [
            'asset_id' => $asset->getKey(),
            'agent_id' => $secondAgent->getKey(),
            'service_id' => '',
            'assigned_at' => now()->toDateTimeString(),
            'reason' => 'Doublon MySQL',
        ])->assertSessionHasErrors('asset_id');

        $this->post(route('assignments.transfer', $asset), [
            'agent_id' => '',
            'service_id' => $service->getKey(),
            'assigned_at' => $transferredAt,
            'reason' => 'Transfert MySQL',
        ])->assertRedirect(route('assignments.show', $asset));

        $this->post(route('assignments.return', $asset), [
            'returned_at' => now()->format('Y-m-d\TH:i'),
            'reason' => 'Restitution MySQL',
        ])->assertRedirect(route('assignments.show', $asset));

        $this->assertDatabaseCount('asset_assignments', 2);
        $this->assertDatabaseHas('asset_assignments', [
            'asset_id' => $asset->getKey(),
            'service_id' => $service->getKey(),
            'end_reason' => 'Restitution MySQL',
            'ended_by' => $actor->getKey(),
        ]);
        $this->get(route('assignments.show', $asset))
            ->assertOk()
            ->assertSee('Affectation MySQL')
            ->assertSee('Transfert MySQL')
            ->assertSee('Restitution MySQL');
    }

    public function test_mysql_asset_crud_filters_and_archive_preserve_histories(): void
    {
        $actor = User::factory()->create(['role' => UserRole::AssetManager]);
        $category = Category::factory()->create();
        $initialLocation = Location::factory()->create();
        $destination = Location::factory()->create();
        $this->actingAs($actor);

        $assetCode = 'MYSQL-ASSET-'.strtoupper(Str::random(8));
        $serialNumber = 'MYSQL-SERIAL-'.strtoupper(Str::random(8));
        $this->get(route('assets.index'))->assertOk();
        $this->get(route('assets.create'))->assertOk();

        $this->post(route('assets.store'), [
            'asset_code' => $assetCode,
            'designation' => 'Bien de test MySQL',
            'serial_number' => $serialNumber,
            'category_id' => $category->getKey(),
            'location_id' => $initialLocation->getKey(),
            'condition' => 'good',
            'status' => 'in_service',
        ])->assertRedirect();

        $asset = Asset::query()->where('asset_code', $assetCode)->firstOrFail();
        $this->assertDatabaseHas('assets', [
            'id' => $asset->getKey(),
            'created_by' => $actor->getKey(),
            'location_id' => $initialLocation->getKey(),
        ]);
        $this->get(route('assets.index', [
            'q' => $assetCode,
            'category_id' => $category->getKey(),
            'location_id' => $initialLocation->getKey(),
            'condition' => 'good',
            'status' => 'in_service',
        ]))->assertOk()->assertSee($assetCode);
        $this->get(route('assets.index', ['q' => $serialNumber]))
            ->assertOk()
            ->assertSee($assetCode);
        $this->get(route('assets.show', $asset))
            ->assertOk()
            ->assertSee('Bien de test MySQL')
            ->assertSee('Historique de localisation')
            ->assertSee('href="'.e(route('assets.edit', $asset)).'"', false)
            ->assertSee('action="'.e(route('assets.destroy', $asset)).'"', false);

        $this->put(route('assets.update', $asset), [
            'asset_code' => $assetCode,
            'designation' => 'Bien MySQL modifié',
            'category_id' => $category->getKey(),
            'location_id' => $destination->getKey(),
            'condition' => 'good',
            'status' => 'in_service',
        ])->assertRedirect(route('assets.show', $asset));

        $this->assertDatabaseHas('asset_location_histories', [
            'asset_id' => $asset->getKey(),
            'from_location_id' => $initialLocation->getKey(),
            'to_location_id' => $destination->getKey(),
            'recorded_by' => $actor->getKey(),
        ]);
        $this->assertSame('Bien MySQL modifié', $asset->fresh()->designation);

        $agent = Agent::factory()->create();
        $assignments = app(AssetAssignmentService::class);
        $assignments->assign(
            $asset,
            $agent->getKey(),
            null,
            $actor,
            now()->subMinute()->format('Y-m-d\TH:i'),
            'Affectation MySQL à conserver',
            null,
        );
        $this->from(route('assets.show', $asset))
            ->delete(route('assets.destroy', $asset))
            ->assertRedirect(route('assets.show', $asset))
            ->assertSessionHasErrors('asset');
        $this->assertNotSoftDeleted($asset);

        $assignments->restitute(
            $asset,
            $actor,
            now()->format('Y-m-d\TH:i'),
            'Restitution MySQL à conserver',
        );

        $this->delete(route('assets.destroy', $asset))
            ->assertRedirect(route('assets.index'));

        $this->assertSoftDeleted($asset);
        $this->assertSame(
            2,
            DB::table('asset_location_histories')->where('asset_id', $asset->getKey())->count(),
        );
        $this->assertSame(
            1,
            DB::table('asset_assignments')->where('asset_id', $asset->getKey())->count(),
        );
        $this->assertDatabaseHas('asset_assignments', [
            'asset_id' => $asset->getKey(),
            'end_reason' => 'Restitution MySQL à conserver',
            'ended_by' => $actor->getKey(),
        ]);
        $this->get(route('assets.show', $asset))->assertNotFound();
    }
}
