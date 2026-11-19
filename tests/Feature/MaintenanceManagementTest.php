<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\MaintenanceFrequency;
use App\Enums\MaintenanceRequestStatus;
use App\Enums\UserRole;
use App\Models\Agent;
use App\Models\Asset;
use App\Models\MaintenanceIntervention;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MaintenanceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_corrective_request_lifecycle_is_audited_and_keeps_asset_status_separate(): void
    {
        $reporter = User::factory()->create(['role' => UserRole::Viewer]);
        $manager = User::factory()->create(['role' => UserRole::AssetManager]);
        $technician = User::factory()->create(['role' => UserRole::Viewer]);
        $agent = Agent::factory()->create(['user_id' => $technician->getKey(), 'is_active' => true]);
        $asset = Asset::factory()->create(['status' => AssetStatus::InService]);

        $this->actingAs($reporter)->post(route('maintenance.requests.store'), [
            'asset_id' => $asset->getKey(), 'title' => 'Panne imprimante', 'description' => 'Le bac papier ne s’entraîne plus.', 'priority' => 'high',
        ])->assertRedirect();
        $request = MaintenanceRequest::query()->firstOrFail();
        $this->assertSame(MaintenanceRequestStatus::New, $request->status);
        $this->assertDatabaseHas('maintenance_histories', ['request_id' => $request->getKey(), 'event_type' => 'request.created']);

        $this->actingAs($manager)->post(route('maintenance.requests.validate', $request), ['decision' => 'approve', 'observation' => 'Diagnostic demandé'])->assertRedirect();
        $this->post(route('maintenance.requests.assign', $request), ['agent_id' => $agent->getKey(), 'planned_at' => now()->addDay()->format('Y-m-d\TH:i')])->assertRedirect();
        $intervention = MaintenanceIntervention::query()->where('request_id', $request->getKey())->firstOrFail();
        $this->get(route('maintenance.requests.work-order.pdf', $request))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('maintenance.interventions.pdf', $intervention))->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $this->actingAs($technician)->post(route('maintenance.interventions.start', $intervention))->assertRedirect();
        $this->post(route('maintenance.interventions.finish', $intervention), [
            'diagnosis' => 'Courroie usée', 'work_performed' => 'Remplacement de la courroie.', 'parts_used' => 'Courroie modèle A',
            'labor_cost' => '2500.00', 'parts_cost' => '12000.00', 'report' => 'Essai fonctionnel réussi.', 'observations' => '',
        ])->assertRedirect();
        $this->assertSame(MaintenanceRequestStatus::PendingClosure, $request->fresh()->status);
        $this->actingAs($manager)->post(route('maintenance.requests.close', $request), ['observation' => 'Remise en service contrôlée'])->assertRedirect();

        $this->assertSame(MaintenanceRequestStatus::Completed, $request->fresh()->status);
        $this->assertSame('14500.00', $intervention->fresh()->total_cost);
        $this->assertSame(AssetStatus::InService, $asset->fresh()->status);
        $this->assertDatabaseHas('maintenance_histories', ['request_id' => $request->getKey(), 'event_type' => 'request.closed']);
        $this->actingAs($manager)->get(route('maintenance.requests.pdf', $request))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('maintenance.interventions.report.pdf', $intervention))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_duplicate_corrective_requests_are_rejected_until_the_existing_one_is_closed(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $asset = Asset::factory()->create();
        $payload = ['asset_id' => $asset->getKey(), 'title' => 'Panne', 'description' => 'Description panne', 'priority' => 'normal'];

        $this->actingAs($viewer)->post(route('maintenance.requests.store'), $payload)->assertRedirect();
        $this->post(route('maintenance.requests.store'), $payload)->assertSessionHasErrors('asset_id');
        $this->assertDatabaseCount('maintenance_requests', 1);
    }

    public function test_preventive_due_request_is_deduplicated_and_advances_next_date_after_approval(): void
    {
        $manager = User::factory()->create(['role' => UserRole::AssetManager]);
        $technician = User::factory()->create(['role' => UserRole::Viewer]);
        $agent = Agent::factory()->create(['user_id' => $technician->getKey(), 'is_active' => true]);
        $asset = Asset::factory()->create();
        $this->actingAs($manager)->post(route('maintenance.schedules.store'), [
            'asset_id' => $asset->getKey(), 'assigned_agent_id' => $agent->getKey(), 'title' => 'Entretien climatiseur',
            'description' => 'Nettoyage périodique', 'frequency' => MaintenanceFrequency::Quarterly->value,
            'next_due_on' => today()->toDateString(),
        ])->assertRedirect();
        $schedule = MaintenanceSchedule::query()->firstOrFail();

        $this->post(route('maintenance.schedules.due', $schedule))->assertRedirect();
        $request = MaintenanceRequest::query()->firstOrFail();
        $this->post(route('maintenance.schedules.due', $schedule))->assertSessionHasErrors('schedule');
        $this->post(route('maintenance.requests.validate', $request), ['decision' => 'approve'])->assertRedirect();
        $this->post(route('maintenance.requests.assign', $request), ['agent_id' => $agent->getKey(), 'planned_at' => now()->format('Y-m-d\TH:i')])->assertRedirect();
        $intervention = MaintenanceIntervention::query()->where('request_id', $request->getKey())->firstOrFail();
        $this->actingAs($technician)->post(route('maintenance.interventions.start', $intervention))->assertRedirect();
        $this->post(route('maintenance.interventions.finish', $intervention), [
            'work_performed' => 'Entretien préventif terminé', 'labor_cost' => '0', 'parts_cost' => '0', 'report' => 'Contrôle réalisé',
        ])->assertRedirect();
        $this->actingAs($manager)->post(route('maintenance.requests.close', $request), [])->assertRedirect();

        $this->assertSame(today()->addMonthsNoOverflow(3)->toDateString(), $schedule->fresh()->next_due_on->toDateString());
        $this->assertSame(today()->toDateString(), $schedule->fresh()->last_completed_on->toDateString());
        $this->actingAs($manager)->patch(route('maintenance.schedules.toggle', $schedule))->assertRedirect();
        $this->assertFalse($schedule->fresh()->is_active);
        $this->assertDatabaseHas('maintenance_histories', ['schedule_id' => $schedule->getKey(), 'event_type' => 'schedule.status_changed']);
    }

    public function test_request_visibility_private_justificatifs_and_role_restrictions(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['role' => UserRole::Viewer]);
        $other = User::factory()->create(['role' => UserRole::Viewer]);
        $manager = User::factory()->create(['role' => UserRole::AssetManager]);
        $asset = Asset::factory()->create();
        $this->actingAs($owner)->post(route('maintenance.requests.store'), ['asset_id' => $asset->getKey(), 'title' => 'Fuite', 'description' => 'Fuite observée', 'priority' => 'urgent'])->assertRedirect();
        $request = MaintenanceRequest::query()->firstOrFail();

        $this->actingAs($other)->get(route('maintenance.requests.show', $request))->assertForbidden();
        $this->get(route('maintenance.schedules'))->assertForbidden();
        $this->get(route('maintenance.costs'))->assertForbidden();
        $this->actingAs($owner)->post(route('maintenance.documents.store', $request), ['document' => UploadedFile::fake()->create('facture.pdf', 100, 'application/pdf')])->assertRedirect();
        $document = $request->documents()->firstOrFail();
        Storage::disk('local')->assertExists($document->path);
        $this->get(route('maintenance.documents.download', $document))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs($other)->get(route('maintenance.documents.download', $document))->assertForbidden();
        $this->actingAs($manager)->get(route('maintenance.costs'))->assertOk();
    }

    public function test_cost_exports_and_asset_fiche_show_open_maintenance_separately(): void
    {
        $manager = User::factory()->create(['role' => UserRole::AssetManager]);
        $asset = Asset::factory()->create();
        $this->actingAs($manager)->post(route('maintenance.requests.store'), ['asset_id' => $asset->getKey(), 'title' => 'Panne test', 'description' => 'Panne de contrôle', 'priority' => 'normal'])->assertRedirect();
        $request = MaintenanceRequest::query()->firstOrFail();

        $this->get(route('assets.show', $asset))->assertOk()->assertSee('Panne / demande ouverte')->assertSee($request->reference);
        $this->get(route('maintenance.costs.pdf'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('maintenance.costs.excel'))->assertOk()->assertHeader('Content-Type', 'application/vnd.ms-excel; charset=UTF-8');
        $this->get(route('maintenance.index', ['status' => 'new']))->assertOk()->assertSee($request->reference);
    }
}
