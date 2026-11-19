<?php

namespace Tests\Feature;

use App\Enums\MaintenanceRequestStatus;
use App\Enums\UserRole;
use App\Models\Agent;
use App\Models\Asset;
use App\Models\MaintenanceIntervention;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MySqlMaintenanceIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Ce scénario d’intégration nécessite MySQL.');
        }
    }

    public function test_mysql_corrective_cycle_preserves_asset_status_and_writes_history(): void
    {
        $reporter = User::factory()->create(['role' => UserRole::Viewer]);
        $manager = User::factory()->create(['role' => UserRole::AssetManager]);
        $technician = User::factory()->create(['role' => UserRole::Viewer]);
        $agent = Agent::factory()->create(['user_id' => $technician->getKey(), 'is_active' => true]);
        $asset = Asset::factory()->create();

        $this->actingAs($reporter)->post(route('maintenance.requests.store'), [
            'asset_id' => $asset->getKey(), 'title' => 'Contrôle MySQL', 'description' => 'Signalement de panne MySQL.', 'priority' => 'normal',
        ])->assertRedirect();
        $request = MaintenanceRequest::query()->firstOrFail();
        $this->actingAs($manager)->post(route('maintenance.requests.validate', $request), ['decision' => 'approve'])->assertRedirect();
        $this->post(route('maintenance.requests.assign', $request), ['agent_id' => $agent->getKey(), 'planned_at' => now()->format('Y-m-d\TH:i')])->assertRedirect();
        $intervention = MaintenanceIntervention::query()->where('request_id', $request->getKey())->firstOrFail();
        $this->actingAs($technician)->post(route('maintenance.interventions.start', $intervention))->assertRedirect();
        $this->post(route('maintenance.interventions.finish', $intervention), [
            'work_performed' => 'Contrôle d’intégration terminé.', 'labor_cost' => '0', 'parts_cost' => '0', 'report' => 'Vérification MySQL réussie.',
        ])->assertRedirect();
        $this->actingAs($manager)->post(route('maintenance.requests.close', $request), [])->assertRedirect();

        $this->assertSame(MaintenanceRequestStatus::Completed, $request->fresh()->status);
        $this->assertSame($asset->status, $asset->fresh()->status);
        $this->assertDatabaseHas('maintenance_histories', ['request_id' => $request->getKey(), 'event_type' => 'request.closed']);
    }
}
