<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Agent;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\Location;
use App\Models\Service;
use App\Models\User;
use App\Services\AssetAssignmentService;
use App\Services\AssetLocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AssetAssignmentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_asset_can_be_assigned_transferred_and_returned_with_audited_history(): void
    {
        $actor = User::factory()->create(['role' => UserRole::AssetManager]);
        $this->actingAs($actor);
        $asset = Asset::factory()->create();
        $agent = Agent::factory()->create();
        $service = Service::factory()->create();
        $startedAt = now()->subMinutes(2)->format('Y-m-d\TH:i');
        $transferredAt = now()->subMinute()->format('Y-m-d\TH:i');
        $returnedAt = now()->format('Y-m-d\TH:i');

        $this->post(route('assignments.store'), [
            'asset_id' => $asset->getKey(),
            'agent_id' => $agent->getKey(),
            'service_id' => '',
            'assigned_at' => $startedAt,
            'reason' => 'Mise à disposition',
            'observations' => 'Premier enregistrement',
        ])->assertRedirect(route('assignments.show', $asset));

        $initial = AssetAssignment::query()->where('asset_id', $asset->getKey())->firstOrFail();
        $this->assertSame($actor->getKey(), $initial->assigned_by);
        $this->assertSame($agent->getKey(), $initial->agent_id);

        $this->post(route('assignments.transfer', $asset), [
            'agent_id' => '',
            'service_id' => $service->getKey(),
            'assigned_at' => $transferredAt,
            'reason' => 'Transfert au service',
            'observations' => 'Changement de détenteur',
        ])->assertRedirect(route('assignments.show', $asset));

        $initial->refresh();
        $active = AssetAssignment::query()->where('asset_id', $asset->getKey())->whereNull('ended_at')->firstOrFail();
        $this->assertSame($actor->getKey(), $initial->ended_by);
        $this->assertSame($actor->getKey(), $active->assigned_by);
        $this->assertSame($service->getKey(), $active->service_id);

        $this->post(route('assignments.return', $asset), [
            'returned_at' => $returnedAt,
            'reason' => 'Restitution',
            'received_by_name' => 'Agent de réception',
            'return_condition' => 'good',
            'return_observations' => 'Équipement complet',
        ])->assertRedirect(route('assignments.show', $asset));

        $this->assertDatabaseCount('asset_assignments', 2);
        $this->assertDatabaseHas('asset_assignments', [
            'id' => $active->getKey(),
            'ended_by' => $actor->getKey(),
            'end_reason' => 'Restitution',
            'received_by_name' => 'Agent de réception',
            'return_condition' => 'good',
        ]);
        $this->get(route('assignments.show', $asset))
            ->assertOk()
            ->assertSee('Historique des mouvements')
            ->assertSee('Mise à disposition')
            ->assertSee('Transfert au service')
            ->assertSee('Restitution');

        $this->get(route('assignments.unassigned'))->assertOk()->assertSee($asset->asset_code);

        $this->expectException(LogicException::class);
        $initial->delete();
    }

    public function test_asset_cannot_receive_a_second_active_assignment(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::AssetManager]));
        $asset = Asset::factory()->create();
        $firstAgent = Agent::factory()->create();
        $secondAgent = Agent::factory()->create();

        $this->get(route('assignments.create'))->assertOk();

        app(AssetAssignmentService::class)->assign(
            $asset,
            $firstAgent->getKey(),
            null,
            auth()->user(),
            now()->subMinute()->toDateTimeString(),
            'Affectation initiale',
            null,
        );

        $this->from(route('assignments.create'))
            ->post(route('assignments.store'), [
                'asset_id' => $asset->getKey(),
                'agent_id' => $secondAgent->getKey(),
                'service_id' => '',
                'assigned_at' => now()->toDateTimeString(),
                'reason' => 'Affectation en doublon',
            ])
            ->assertRedirect(route('assignments.create'))
            ->assertSessionHasErrors('asset_id');

        $this->assertDatabaseCount('asset_assignments', 1);
    }

    public function test_assignment_requires_exactly_one_active_agent_or_service(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::AssetManager]));
        $asset = Asset::factory()->create();
        $agent = Agent::factory()->create();
        $service = Service::factory()->create();

        $this->from(route('assignments.create'))
            ->post(route('assignments.store'), [
                'asset_id' => $asset->getKey(),
                'agent_id' => $agent->getKey(),
                'service_id' => $service->getKey(),
                'assigned_at' => now()->toDateTimeString(),
                'reason' => 'Détenteur ambigu',
            ])
            ->assertSessionHasErrors('agent_id');

        $this->assertDatabaseCount('asset_assignments', 0);
    }

    public function test_user_can_consult_active_assets_by_agent_or_service(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Viewer]);
        $this->actingAs($manager);
        $asset = Asset::factory()->create(['asset_code' => 'ASSIGNED-AGENT-01']);
        $agent = Agent::factory()->create();
        $service = Service::factory()->create();

        app(AssetAssignmentService::class)->assign(
            $asset,
            $agent->getKey(),
            null,
            $manager,
            now()->toDateTimeString(),
            'Consultation',
            null,
        );

        $this->get(route('assignments.agent', $agent))
            ->assertOk()
            ->assertSee($asset->asset_code);
        $this->get(route('assignments.service', $service))
            ->assertOk()
            ->assertDontSee($asset->asset_code);
    }

    public function test_physical_move_is_separate_audited_and_role_protected(): void
    {
        $manager = User::factory()->create(['role' => UserRole::AssetManager]);
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $asset = Asset::factory()->create();
        $oldLocation = $asset->location;
        $newLocation = Location::factory()->create();

        $this->actingAs($manager)->post(route('assignments.move', $asset), [
            'location_id' => $newLocation->getKey(),
            'moved_at' => now()->subMinute()->format('Y-m-d\\TH:i'),
            'reason' => 'Déplacement de bureau',
            'notes' => 'Déplacement physique uniquement',
        ])->assertRedirect(route('assignments.show', $asset));

        $this->assertSame($newLocation->getKey(), $asset->fresh()->location_id);
        $this->assertDatabaseHas('asset_location_histories', [
            'asset_id' => $asset->getKey(),
            'from_location_id' => $oldLocation->getKey(),
            'to_location_id' => $newLocation->getKey(),
            'reason' => 'Déplacement de bureau',
            'notes' => 'Déplacement physique uniquement',
            'recorded_by' => $manager->getKey(),
        ]);

        $this->actingAs($viewer)->post(route('assignments.move', $asset), [
            'location_id' => $oldLocation->getKey(),
            'moved_at' => now()->format('Y-m-d\\TH:i'),
            'reason' => 'Non autorisé',
        ])->assertForbidden();

        $this->get(route('assignments.show', $asset))->assertOk()->assertSee('Déplacements physiques')->assertSee('Déplacement de bureau');
    }

    public function test_physical_move_date_cannot_precede_last_movement(): void
    {
        $actor = User::factory()->create(['role' => UserRole::AssetManager]);
        $asset = Asset::factory()->create();
        $destination = Location::factory()->create();
        app(AssetLocationService::class)->move($asset, $destination, $actor, 'Mouvement récent', now()->subDay()->toDateTimeString());

        $this->actingAs($actor)->from(route('assignments.show', $asset))->post(route('assignments.move', $asset), [
            'location_id' => $asset->location->getKey(),
            'moved_at' => now()->subDays(2)->format('Y-m-d\\TH:i'),
            'reason' => 'Date antérieure',
        ])->assertSessionHasErrors('moved_at');

        $this->assertDatabaseCount('asset_location_histories', 1);
    }
}
