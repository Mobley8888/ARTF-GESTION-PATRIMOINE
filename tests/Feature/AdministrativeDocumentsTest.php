<?php

namespace Tests\Feature;

use App\Enums\InventoryCampaignStatus;
use App\Enums\InventoryItemStatus;
use App\Enums\UserRole;
use App\Models\Agent;
use App\Models\Asset;
use App\Models\Direction;
use App\Models\InventoryCampaign;
use App\Models\InventoryCampaignScope;
use App\Models\InventoryItem;
use App\Models\InventoryReview;
use App\Models\InventoryTask;
use App\Models\Service;
use App\Models\User;
use App\Services\AssetAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdministrativeDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_asset_and_assignment_sheets_render_and_download_as_pdf(): void
    {
        $manager = User::factory()->create(['role' => UserRole::AssetManager]);
        $direction = Direction::factory()->create(['name' => 'Direction Administrative']);
        $service = Service::factory()->create(['direction_id' => $direction->getKey(), 'name' => 'Service Comptable']);
        $agent = Agent::factory()->create(['service_id' => $service->getKey(), 'first_name' => 'Awa', 'last_name' => 'Mensah']);
        $asset = Asset::factory()->create(['asset_code' => 'ARTF-DOC-001', 'designation' => 'Ordinateur documentaire']);
        $assignment = app(AssetAssignmentService::class)->assign($asset, $agent->getKey(), null, $manager, now()->subDay()->toDateTimeString(), 'Dotation', 'Pour les éditions');

        $this->actingAs($manager)->get(route('documents.assets.show', $asset))->assertOk()->assertSee('Fiche individuelle du bien')->assertSee('Ordinateur documentaire')->assertSee('Awa Mensah');
        $this->get(route('documents.assets.pdf', $asset))->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertSee('%PDF-1.4', false)->assertSee('REPUBLIQUE DU CONGO', false);
        $this->get(route('documents.assignments.show', $assignment))->assertOk()->assertSee('Fiche d’affectation')->assertSee('Service Comptable');
        $this->get(route('documents.assignments.pdf', $assignment))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_transfer_and_restitution_reports_use_saved_movement_details(): void
    {
        $manager = User::factory()->create(['role' => UserRole::AssetManager]);
        $service = Service::factory()->create();
        $formerAgent = Agent::factory()->create(['service_id' => $service->getKey(), 'first_name' => 'Ancien', 'last_name' => 'Détenteur']);
        $newAgent = Agent::factory()->create(['service_id' => $service->getKey(), 'first_name' => 'Nouveau', 'last_name' => 'Détenteur']);
        $asset = Asset::factory()->create(['asset_code' => 'ARTF-TRF-001']);
        $assignments = app(AssetAssignmentService::class);
        $first = $assignments->assign($asset, $formerAgent->getKey(), null, $manager, now()->subDays(4)->toDateTimeString(), 'Dotation', null);
        $transfer = $assignments->transfer($asset, $newAgent->getKey(), null, $manager, now()->subDays(2)->toDateTimeString(), 'Changement de service', 'Transfert contrôlé');

        $this->actingAs($manager)->get(route('documents.transfers.show', $transfer))->assertOk()->assertSee('Procès-verbal de transfert')->assertSee('Ancien Détenteur')->assertSee('Nouveau Détenteur');
        $this->get(route('documents.transfers.pdf', $transfer))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('documents.restitutions.show', $first))->assertNotFound();

        $assignments->restitute($asset, $manager, now()->toDateTimeString(), 'Retour au magasin', 'Agent réceptionnaire', 'good', 'Équipement complet');
        $closedAssignment = $asset->assignments()->where('movement_type', 'transfer')->firstOrFail();
        $this->get(route('documents.restitutions.show', $closedAssignment))->assertOk()->assertSee('Procès-verbal de restitution')->assertSee('Équipement complet');
        $this->get(route('documents.restitutions.pdf', $closedAssignment))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_asset_register_filters_and_exports_to_pdf_and_excel_with_role_protection(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $matching = Asset::factory()->create(['asset_code' => 'ARTF-REGISTER-001', 'condition' => 'good']);
        Asset::factory()->create(['asset_code' => 'ARTF-REGISTER-002', 'condition' => 'poor']);

        $this->actingAs($viewer)->get(route('reports.index', ['condition' => 'good']))->assertOk()->assertSee('ARTF-REGISTER-001')->assertDontSee('ARTF-REGISTER-002');
        $this->get(route('reports.assets.pdf', ['condition' => 'good']))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('reports.assets.excel', ['condition' => 'good']))->assertOk()->assertHeader('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')->assertSee('ARTF-REGISTER-001');
        $this->actingAs(User::factory()->create(['role' => UserRole::Viewer]))->get(route('documents.assets.pdf', $matching))->assertOk();
    }

    public function test_inventory_finding_is_assignment_scoped_and_closed_campaign_has_closure_pv(): void
    {
        $operator = User::factory()->create(['role' => UserRole::Viewer]);
        $otherOperator = User::factory()->create(['role' => UserRole::Viewer]);
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        $agent = Agent::factory()->create(['user_id' => $operator->getKey()]);
        Agent::factory()->create(['user_id' => $otherOperator->getKey()]);
        $campaign = InventoryCampaign::query()->create([
            'reference' => 'INV-DOC-001', 'title' => 'Inventaire documentaire', 'starts_on' => now()->subDay()->toDateString(), 'ends_on' => now()->toDateString(),
            'status' => InventoryCampaignStatus::Closed, 'responsible_user_id' => $administrator->getKey(), 'created_by' => $administrator->getKey(),
            'validated_by' => $administrator->getKey(), 'closed_by' => $administrator->getKey(), 'validated_at' => now(), 'closed_at' => now(),
        ]);
        $scope = InventoryCampaignScope::query()->create(['campaign_id' => $campaign->getKey(), 'scope_type' => 'global']);
        $task = InventoryTask::query()->create(['campaign_id' => $campaign->getKey(), 'scope_id' => $scope->getKey(), 'agent_id' => $agent->getKey(), 'assigned_by' => $administrator->getKey(), 'status' => 'submitted', 'submitted_at' => now()]);
        $asset = Asset::factory()->create(['asset_code' => 'ARTF-INV-DOC-001']);
        $item = InventoryItem::query()->create([
            'campaign_id' => $campaign->getKey(), 'task_id' => $task->getKey(), 'asset_id' => $asset->getKey(), 'expected_snapshot' => ['asset_code' => $asset->asset_code, 'designation' => $asset->designation],
            'status' => InventoryItemStatus::PresentAnomaly, 'physical_anomaly' => 'Étiquette détériorée', 'observed_by' => $operator->getKey(), 'observed_at' => now(),
        ]);
        InventoryReview::query()->create(['campaign_id' => $campaign->getKey(), 'reviewed_by' => $administrator->getKey(), 'step' => 'closure', 'decision' => 'closed', 'observations' => 'Bilan arrêté', 'reviewed_at' => now()]);

        $this->actingAs($operator)->get(route('documents.inventory.findings.show', $item))->assertOk()->assertSee('Étiquette détériorée');
        $this->get(route('documents.inventory.findings.pdf', $item))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($otherOperator)->get(route('documents.inventory.findings.show', $item))->assertNotFound();
        $this->actingAs($administrator)->get(route('documents.inventory.closure.show', $campaign))->assertOk()->assertSee('Procès-verbal de clôture');
        $this->get(route('documents.inventory.closure.pdf', $campaign))->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertSee('REPUBLIQUE DU CONGO', false);
    }
}
