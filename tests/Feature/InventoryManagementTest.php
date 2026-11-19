<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Agent;
use App\Models\Asset;
use App\Models\Direction;
use App\Models\InventoryCampaign;
use App\Models\Service;
use App\Models\User;
use App\Services\AssetAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\TestCase;

class InventoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_can_be_created_with_multiple_scopes_and_preview_deduplicates_assets(): void
    {
        $manager = User::factory()->create(['role' => UserRole::AssetManager]);
        $this->actingAs($manager);
        $direction = Direction::factory()->create();
        $service = Service::factory()->create(['direction_id' => $direction->getKey()]);
        $agent = Agent::factory()->create(['service_id' => $service->getKey()]);
        $asset = Asset::factory()->create();
        app(AssetAssignmentService::class)->assign($asset, null, $service->getKey(), $manager, now()->subDay()->toDateTimeString(), 'Périmètre de test', null);

        $payload = [
            'reference' => 'INV-MULTI-001',
            'title' => 'Inventaire multi-périmètre',
            'starts_on' => now()->format('Y-m-d'),
            'ends_on' => now()->addWeek()->format('Y-m-d'),
            'direction_ids' => [$direction->getKey()],
            'service_ids' => [$service->getKey()],
            'location_ids' => [$asset->location_id],
        ];

        $this->from(route('inventories.create'))->post(route('inventories.preview'), $payload)
            ->assertRedirect(route('inventories.create'))
            ->assertSessionHas('inventory_preview_count', 1);

        $this->post(route('inventories.store'), $payload)->assertRedirect();
        $campaign = InventoryCampaign::query()->where('reference', 'INV-MULTI-001')->firstOrFail();
        $this->assertCount(3, $campaign->scopes);
        $this->assertSame(UserRole::AssetManager, $campaign->responsible->role);
        $this->assertDatabaseCount('assets', 1);
    }

    public function test_inventory_workflow_records_findings_evidence_and_freezes_assets_and_closed_campaign(): void
    {
        Storage::fake('private');
        $manager = User::factory()->create(['role' => UserRole::AssetManager]);
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $operator = User::factory()->create(['role' => UserRole::Viewer]);
        $agent = Agent::factory()->create(['user_id' => $operator->getKey()]);
        $asset = Asset::factory()->create(['asset_code' => 'INV-ASSET-01', 'designation' => 'Bien test inventaire']);
        $originalLocationId = $asset->location_id;

        $this->actingAs($manager)->post(route('inventories.store'), [
            'reference' => 'INV-WORKFLOW-001',
            'title' => 'Campagne de test workflow',
            'starts_on' => now()->format('Y-m-d'),
            'ends_on' => now()->addWeek()->format('Y-m-d'),
            'global_scope' => '1',
        ])->assertRedirect();
        $campaign = InventoryCampaign::query()->where('reference', 'INV-WORKFLOW-001')->firstOrFail();
        $scope = $campaign->scopes()->firstOrFail();

        $this->post(route('inventories.tasks.store', $campaign), [
            'scope_id' => $scope->getKey(),
            'agent_id' => $agent->getKey(),
            'due_on' => now()->addDay()->format('Y-m-d'),
        ])->assertRedirect();

        $this->post(route('inventories.launch', $campaign))->assertRedirect();
        $campaign->refresh();
        $this->assertSame('in_progress', $campaign->status->value);
        $item = $campaign->items()->where('asset_id', $asset->getKey())->firstOrFail();
        $this->assertSame('INV-ASSET-01', $item->expected_snapshot['asset_code']);

        $this->actingAs($operator)->put(route('inventories.capture', $item), [
            'status' => 'present_anomaly',
            'observed_asset_code' => 'INV-ASSET-01',
            'observed_designation' => 'Bien test inventaire',
            'observed_location_id' => $originalLocationId,
            'physical_condition' => 'poor',
            'physical_anomaly' => 'Boîtier endommagé',
            'administrative_anomaly' => 'Étiquette illisible',
            'observations' => 'Constat effectué sur place',
        ])->assertRedirect();

        $this->post(route('inventories.evidence.store', $item), [
            'evidence' => [UploadedFile::fake()->image('photo.jpg')],
        ])->assertRedirect();
        $evidence = $item->evidence()->firstOrFail();
        Storage::disk('private')->assertExists($evidence->path);
        $this->get(route('inventories.evidence.download', $evidence))->assertOk();

        $this->post(route('inventories.discover', $campaign->tasks()->first()), [
            'status' => 'discovered',
            'observed_asset_code' => 'FOUND-001',
            'observed_designation' => 'Équipement non référencé',
            'observations' => 'Trouvé dans le magasin',
        ])->assertRedirect();
        $this->from(route('inventories.items', $campaign))->post(route('inventories.discover', $campaign->tasks()->first()), [
            'status' => 'discovered',
            'observed_asset_code' => 'FOUND-001',
            'observed_designation' => 'Doublon découvert',
        ])->assertSessionHasErrors('observed_asset_code');
        $this->assertDatabaseHas('inventory_items', [
            'campaign_id' => $campaign->getKey(),
            'asset_id' => null,
            'observed_asset_code' => 'FOUND-001',
            'status' => 'discovered',
        ]);
        $this->assertSame($originalLocationId, $asset->fresh()->location_id);
        $this->assertSame('INV-ASSET-01', $asset->fresh()->asset_code);

        $this->post(route('inventories.tasks.submit', $campaign->tasks()->first()))->assertRedirect();
        $campaign->refresh();
        $this->assertSame('to_verify', $campaign->status->value);
        $this->actingAs($manager)->post(route('inventories.verify', $campaign), ['decision' => 'approved', 'observations' => 'Vérifié'])->assertRedirect();
        $this->actingAs($admin)->post(route('inventories.validate', $campaign), ['observations' => 'Validé'])->assertRedirect();
        $this->post(route('inventories.close', $campaign), ['observations' => 'Clôturé'])->assertRedirect();
        $this->assertSame('closed', $campaign->fresh()->status->value);
        $this->assertDatabaseHas('inventory_reviews', ['campaign_id' => $campaign->getKey(), 'step' => 'closure', 'decision' => 'closed']);
        $this->assertDatabaseHas('inventory_reviews', ['campaign_id' => $campaign->getKey(), 'step' => 'task_assignment', 'decision' => 'assigned']);
        $this->assertDatabaseHas('inventory_reviews', ['campaign_id' => $campaign->getKey(), 'step' => 'item_capture', 'decision' => 'updated']);
        $this->assertDatabaseHas('inventory_reviews', ['campaign_id' => $campaign->getKey(), 'step' => 'asset_discovery', 'decision' => 'recorded']);

        $this->assertThrows(fn () => $campaign->delete(), LogicException::class);
        $this->get(route('inventories.show', $campaign))->assertOk();
        $this->actingAs($manager)->get(route('inventories.report.excel', $campaign))->assertOk()->assertHeader('Content-Type', 'application/vnd.ms-excel; charset=UTF-8');
        $pdf = $this->get(route('inventories.report.pdf', $campaign))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('LISTE DES ECARTS', $pdf->getContent());
    }

    public function test_inventory_permissions_separate_management_verification_and_validation(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $manager = User::factory()->create(['role' => UserRole::AssetManager]);
        $admin = User::factory()->create(['role' => UserRole::Administrator]);

        $this->actingAs($viewer)->get(route('inventories.index'))->assertOk();
        $this->get(route('inventories.create'))->assertForbidden();
        $this->actingAs($admin)->get(route('inventories.create'))->assertForbidden();
        $this->actingAs($manager)->get(route('inventories.create'))->assertOk();

        $this->actingAs($manager)->post(route('inventories.store'), [
            'reference' => 'INV-PERM-001',
            'title' => 'Permissions',
            'starts_on' => now()->format('Y-m-d'),
            'ends_on' => now()->addDay()->format('Y-m-d'),
            'global_scope' => '1',
        ])->assertRedirect();
        $campaign = InventoryCampaign::query()->where('reference', 'INV-PERM-001')->firstOrFail();
        $this->actingAs($viewer)->post(route('inventories.validate', $campaign))->assertForbidden();
        $this->actingAs($manager)->post(route('inventories.close', $campaign))->assertForbidden();
    }

    public function test_draft_campaign_can_be_edited_without_changing_an_assigned_scope(): void
    {
        $manager = User::factory()->create(['role' => UserRole::AssetManager]);
        $operator = User::factory()->create(['role' => UserRole::Viewer]);
        $agent = Agent::factory()->create(['user_id' => $operator->getKey()]);
        $this->actingAs($manager)->post(route('inventories.store'), [
            'reference' => 'INV-EDIT-001',
            'title' => 'Brouillon initial',
            'starts_on' => now()->format('Y-m-d'),
            'ends_on' => now()->addWeek()->format('Y-m-d'),
            'global_scope' => '1',
        ])->assertRedirect();
        $campaign = InventoryCampaign::query()->where('reference', 'INV-EDIT-001')->firstOrFail();
        $scopeId = $campaign->scopes()->value('id');
        $this->post(route('inventories.tasks.store', $campaign), ['scope_id' => $scopeId, 'agent_id' => $agent->getKey()])->assertRedirect();

        $this->get(route('inventories.edit', $campaign))->assertOk()->assertSee('périmètre est verrouillé');
        $this->put(route('inventories.update', $campaign), [
            'reference' => 'INV-EDIT-001',
            'title' => 'Brouillon modifié',
            'starts_on' => now()->format('Y-m-d'),
            'ends_on' => now()->addWeeks(2)->format('Y-m-d'),
            'global_scope' => '1',
        ])->assertRedirect(route('inventories.show', $campaign));

        $this->assertSame('Brouillon modifié', $campaign->fresh()->title);
        $this->assertSame($scopeId, $campaign->scopes()->value('id'));
        $this->assertDatabaseHas('inventory_reviews', ['campaign_id' => $campaign->getKey(), 'step' => 'campaign_update']);
    }

    public function test_assigned_agent_only_sees_their_campaign_missions(): void
    {
        $manager = User::factory()->create(['role' => UserRole::AssetManager]);
        $operator = User::factory()->create(['role' => UserRole::Viewer]);
        $otherOperator = User::factory()->create(['role' => UserRole::Viewer]);
        $agent = Agent::factory()->create(['user_id' => $operator->getKey()]);
        Agent::factory()->create(['user_id' => $otherOperator->getKey()]);
        Asset::factory()->create();

        $this->actingAs($manager)->post(route('inventories.store'), [
            'reference' => 'INV-AGENT-001',
            'title' => 'Périmètre agent',
            'starts_on' => now()->format('Y-m-d'),
            'ends_on' => now()->addWeek()->format('Y-m-d'),
            'global_scope' => '1',
        ])->assertRedirect();
        $campaign = InventoryCampaign::query()->where('reference', 'INV-AGENT-001')->firstOrFail();
        $this->post(route('inventories.tasks.store', $campaign), ['scope_id' => $campaign->scopes()->value('id'), 'agent_id' => $agent->getKey()])->assertRedirect();
        $this->post(route('inventories.launch', $campaign))->assertRedirect();

        $this->actingAs($operator)->get(route('inventories.index'))->assertOk()->assertSee('INV-AGENT-001');
        $this->get(route('inventories.items', $campaign))->assertOk();
        $this->get(route('inventories.show', $campaign))->assertOk()->assertSee('Progression des constats');
        $this->actingAs($otherOperator)->get(route('inventories.show', $campaign))->assertNotFound();
        $this->get(route('inventories.items', $campaign))->assertNotFound();
        $this->get(route('inventories.report.pdf', $campaign))->assertForbidden();
    }

    public function test_campaign_cannot_be_closed_before_hierarchical_validation(): void
    {
        $manager = User::factory()->create(['role' => UserRole::AssetManager]);
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $this->actingAs($manager)->post(route('inventories.store'), [
            'reference' => 'INV-CLOSE-001',
            'title' => 'Campagne non validée',
            'starts_on' => now()->format('Y-m-d'),
            'ends_on' => now()->addWeek()->format('Y-m-d'),
            'global_scope' => '1',
        ])->assertRedirect();
        $campaign = InventoryCampaign::query()->where('reference', 'INV-CLOSE-001')->firstOrFail();

        $this->actingAs($admin)->from(route('inventories.show', $campaign))->post(route('inventories.close', $campaign))->assertSessionHasErrors('campaign');
        $this->assertSame('draft', $campaign->fresh()->status->value);
    }

    public function test_active_agents_without_user_accounts_can_be_searched_assigned_and_reassigned_per_scope(): void
    {
        $manager = User::factory()->create(['role' => UserRole::AssetManager]);
        $direction = Direction::factory()->create(['name' => 'Direction Opérations']);
        $serviceOne = Service::factory()->create(['direction_id' => $direction->getKey(), 'name' => 'Service Logistique']);
        $serviceTwo = Service::factory()->create(['direction_id' => $direction->getKey(), 'name' => 'Service Informatique']);
        $agentOne = Agent::factory()->create([
            'service_id' => $serviceOne->getKey(),
            'user_id' => null,
            'first_name' => 'Amina',
            'last_name' => 'Diallo',
            'employee_code' => 'MAT-1001',
        ]);
        $agentTwo = Agent::factory()->create([
            'service_id' => $serviceTwo->getKey(),
            'user_id' => null,
            'first_name' => 'Karim',
            'last_name' => 'Traoré',
            'employee_code' => 'MAT-1002',
        ]);
        Agent::factory()->create(['is_active' => false, 'user_id' => null, 'employee_code' => 'MAT-INACTIVE']);

        $this->actingAs($manager)->post(route('inventories.store'), [
            'reference' => 'INV-AGENTS-001',
            'title' => 'Affectation par périmètre',
            'starts_on' => now()->format('Y-m-d'),
            'ends_on' => now()->addWeek()->format('Y-m-d'),
            'service_ids' => [$serviceOne->getKey(), $serviceTwo->getKey()],
        ])->assertRedirect();
        $campaign = InventoryCampaign::query()->where('reference', 'INV-AGENTS-001')->firstOrFail();
        $scopes = $campaign->scopes()->orderBy('id')->get();

        $this->post(route('inventories.tasks.store', $campaign), [
            'scope_id' => $scopes[0]->getKey(),
            'agent_id' => $agentOne->getKey(),
            'due_on' => now()->addDays(2)->format('Y-m-d'),
        ])->assertRedirect();
        $this->post(route('inventories.tasks.store', $campaign), [
            'scope_id' => $scopes[1]->getKey(),
            'agent_id' => $agentTwo->getKey(),
            'due_on' => now()->addDays(3)->format('Y-m-d'),
        ])->assertRedirect();

        $page = $this->get(route('inventories.show', $campaign))->assertOk();
        $page->assertSee('Amina Diallo')->assertSee('MAT-1001')->assertSee('Service Logistique')->assertSee('Direction Opérations');
        $page->assertSee('Karim Traoré')->assertSee('MAT-1002')->assertSee('data-agent-search', false);
        $page->assertDontSee('MAT-INACTIVE');
        $page->assertSee('Modifier l’affectation');

        $task = $campaign->tasks()->where('scope_id', $scopes[0]->getKey())->firstOrFail();
        $taskId = $task->getKey();
        $this->post(route('inventories.tasks.store', $campaign), [
            'scope_id' => $scopes[0]->getKey(),
            'agent_id' => $agentTwo->getKey(),
            'due_on' => now()->addDays(4)->format('Y-m-d'),
        ])->assertRedirect();

        $task->refresh();
        $this->assertSame($taskId, $task->getKey());
        $this->assertSame($agentTwo->getKey(), $task->agent_id);
        $this->assertSame($manager->getKey(), $task->assigned_by);
        $this->assertSame(now()->addDays(4)->format('Y-m-d'), $task->due_on->format('Y-m-d'));
        $this->assertDatabaseHas('inventory_reviews', [
            'campaign_id' => $campaign->getKey(),
            'step' => 'task_assignment',
            'decision' => 'reassigned',
        ]);

        $this->post(route('inventories.launch', $campaign))->assertRedirect();
        $this->assertSame('in_progress', $campaign->fresh()->status->value);
    }
}
