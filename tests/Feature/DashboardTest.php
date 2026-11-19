<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\LocationType;
use App\Enums\UserRole;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Location;
use App\Models\Service;
use App\Models\User;
use App\Services\AssetAssignmentService;
use App\Services\AssetLocationService;
use App\Services\DashboardStatistics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_receives_database_counts_and_supervision_metrics(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        User::factory()->create(['role' => UserRole::Administrator]);
        User::factory()->create(['role' => UserRole::Viewer, 'is_active' => false]);

        $category = Category::factory()->create();
        Category::factory()->subcategoryOf($category)->create();
        $site = Location::factory()->create();
        $building = Location::factory()->buildingUnder($site)->create();
        Location::factory()->roomUnder($building)->create();
        Asset::factory()->create([
            'category_id' => $category->getKey(),
            'location_id' => $building->getKey(),
            'status' => AssetStatus::InService,
        ]);

        $this->actingAs($superAdmin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-dashboard-role="super_admin"', false)
            ->assertSee('data-dashboard-metric="assets_total">1', false)
            ->assertSee('data-dashboard-metric="assets_active">1', false)
            ->assertSee('data-dashboard-metric="assets_inactive">0', false)
            ->assertSee('data-dashboard-metric="categories">1', false)
            ->assertSee('data-dashboard-metric="subcategories">1', false)
            ->assertSee('data-dashboard-metric="sites">1', false)
            ->assertSee('data-dashboard-metric="buildings">1', false)
            ->assertSee('data-dashboard-metric="locals">1', false)
            ->assertSee('data-dashboard-metric="users_total">3', false)
            ->assertSee('data-dashboard-metric="users_active">2', false)
            ->assertSee('data-dashboard-metric="users_inactive">1', false)
            ->assertSee('Évolution mensuelle')
            ->assertSee('Administrer les comptes et rôles');
    }

    public function test_administrator_dashboard_uses_its_permissions_without_super_admin_metrics(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);

        $this->actingAs($administrator)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-dashboard-role="admin"', false)
            ->assertSee('data-dashboard-metric="locations_active"', false)
            ->assertSee(route('users.index'))
            ->assertDontSee('data-dashboard-metric="users_total"', false)
            ->assertDontSee('Évolution mensuelle');
    }

    public function test_asset_manager_dashboard_has_operational_actions_but_no_user_administration(): void
    {
        $manager = User::factory()->create(['role' => UserRole::AssetManager]);

        $this->actingAs($manager)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-dashboard-role="asset_manager"', false)
            ->assertSee(route('assets.create'))
            ->assertSee(route('assignments.create'))
            ->assertDontSee(route('users.index'));
    }

    public function test_viewer_dashboard_is_read_only_even_when_a_different_role_is_requested(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        $this->actingAs($viewer)
            ->get(route('dashboard', ['role' => UserRole::SuperAdmin->value]))
            ->assertOk()
            ->assertSee('data-dashboard-role="viewer"', false)
            ->assertSee('Biens consultables')
            ->assertSee(route('assets.index'))
            ->assertSee(route('assignments.index'))
            ->assertSee('id="dashboard-search-q"', false)
            ->assertSee('id="dashboard-search-location"', false)
            ->assertSee('id="dashboard-search-category"', false)
            ->assertDontSee(route('assets.create'))
            ->assertDontSee(route('assignments.create'))
            ->assertDontSee(route('users.index'))
            ->assertDontSee('data-dashboard-metric="users_total"', false)
            ->assertDontSee('Évolution mensuelle');
    }

    public function test_manager_dashboard_counts_available_and_assigned_assets_and_shows_assignment_ends(): void
    {
        $manager = User::factory()->create(['role' => UserRole::AssetManager]);
        $transferredAsset = Asset::factory()->create();
        $returnedAsset = Asset::factory()->create();
        Asset::factory()->create();
        $originalService = Service::factory()->create();
        $destinationService = Service::factory()->create();
        $returnService = Service::factory()->create();
        $assignments = app(AssetAssignmentService::class);

        $assignments->assign(
            $transferredAsset,
            null,
            $originalService->getKey(),
            $manager,
            now()->subMinutes(5)->toDateTimeString(),
            'Affectation avant transfert',
            null,
        );
        $assignments->transfer(
            $transferredAsset,
            null,
            $destinationService->getKey(),
            $manager,
            now()->subMinutes(3)->toDateTimeString(),
            'Transfert enregistré',
            null,
        );
        $assignments->assign(
            $returnedAsset,
            null,
            $returnService->getKey(),
            $manager,
            now()->subMinutes(2)->toDateTimeString(),
            'Affectation avant retour',
            null,
        );
        $assignments->restitute(
            $returnedAsset,
            $manager,
            now()->subMinute()->toDateTimeString(),
            'Restitution enregistrée',
        );

        $this->actingAs($manager)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-dashboard-metric="assets_available">2', false)
            ->assertSee('data-dashboard-metric="assets_assigned">1', false)
            ->assertSee('Transfert enregistré')
            ->assertSee('Fins d’affectation enregistrées')
            ->assertSee('Restitution enregistrée');
    }

    public function test_recent_modifications_are_filtered_by_update_date_not_registration_date(): void
    {
        $manager = User::factory()->create(['role' => UserRole::AssetManager]);
        $asset = Asset::factory()->create([
            'asset_code' => 'DASH-OLD-UPDATED',
            'created_at' => now()->subDays(30),
            'updated_at' => now()->subDays(30),
        ]);
        $asset->forceFill([
            'updated_by' => $manager->getKey(),
            'updated_at' => now(),
        ])->save();

        $this->actingAs($manager)
            ->get(route('dashboard', [
                'from' => now()->toDateString(),
                'to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('data-dashboard-metric="assets_total">0', false)
            ->assertSee('DASH-OLD-UPDATED')
            ->assertSee('Biens récemment modifiés');
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_dashboard_filters_match_real_assets_and_chart_links_open_filtered_lists(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $root = Category::factory()->create();
        $subcategory = Category::factory()->subcategoryOf($root)->create();
        $otherRoot = Category::factory()->create();
        $site = Location::factory()->create();
        $directLocal = Location::factory()->state([
            'parent_id' => $site->getKey(),
            'type' => LocationType::Local,
        ])->create();
        $building = Location::factory()->buildingUnder($site)->create();
        $buildingLocal = Location::factory()->roomUnder($building)->create();
        $otherSite = Location::factory()->create();
        $otherLocal = Location::factory()->state([
            'parent_id' => $otherSite->getKey(),
            'type' => LocationType::Local,
        ])->create();

        $directAsset = Asset::factory()->create([
            'asset_code' => 'DASH-DIRECT',
            'category_id' => $subcategory->getKey(),
            'location_id' => $directLocal->getKey(),
            'status' => AssetStatus::InService,
        ]);
        $buildingAsset = Asset::factory()->create([
            'asset_code' => 'DASH-BUILDING',
            'category_id' => $root->getKey(),
            'location_id' => $buildingLocal->getKey(),
            'status' => AssetStatus::Retired,
        ]);
        Asset::factory()->create([
            'asset_code' => 'DASH-OTHER-SITE',
            'category_id' => $otherRoot->getKey(),
            'location_id' => $otherLocal->getKey(),
        ]);

        $filters = [
            'from' => now()->toDateString(),
            'to' => now()->toDateString(),
            'site_id' => $site->getKey(),
            'category_id' => $root->getKey(),
        ];

        $this->actingAs($viewer)
            ->get(route('dashboard', $filters))
            ->assertOk()
            ->assertSee('data-dashboard-metric="assets_total">2', false)
            ->assertSee('data-dashboard-metric="assets_active">1', false)
            ->assertSee('data-dashboard-metric="assets_inactive">1', false)
            ->assertSee(route('assets.index', ['category_id' => $subcategory->getKey()]))
            ->assertSee(route('assets.index', ['site_id' => $site->getKey()]));

        $this->get(route('dashboard', $filters + [
            'subcategory_id' => $subcategory->getKey(),
            'status' => AssetStatus::InService->value,
        ]))
            ->assertOk()
            ->assertSee('data-dashboard-metric="assets_total">1', false)
            ->assertSee($directAsset->asset_code)
            ->assertDontSee($buildingAsset->asset_code);

        $this->get(route('dashboard', ['to' => now()->toDateString()]))->assertOk();

        $this->get(route('assets.index', ['site_id' => $site->getKey()]))
            ->assertOk()
            ->assertSee($directAsset->asset_code)
            ->assertSee($buildingAsset->asset_code)
            ->assertDontSee('DASH-OTHER-SITE');
    }

    public function test_dashboard_rejects_a_subcategory_from_a_different_root(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $firstRoot = Category::factory()->create();
        $secondRoot = Category::factory()->create();
        $subcategory = Category::factory()->subcategoryOf($firstRoot)->create();
        $this->actingAs($viewer);

        $this->from(route('dashboard'))
            ->get(route('dashboard', [
                'category_id' => $secondRoot->getKey(),
                'subcategory_id' => $subcategory->getKey(),
            ]))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHasErrors('subcategory_id');
    }

    public function test_site_distribution_counts_direct_building_local_and_unlocated_assets_once(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $root = Category::factory()->create();
        $subcategory = Category::factory()->subcategoryOf($root)->create();
        $site = Location::factory()->create();
        $building = Location::factory()->buildingUnder($site)->create();
        $localUnderSite = Location::factory()->state([
            'parent_id' => $site->getKey(),
            'type' => LocationType::Local,
        ])->create();
        $localUnderBuilding = Location::factory()->roomUnder($building)->create();
        $otherSite = Location::factory()->create();
        $otherLocal = Location::factory()->state([
            'parent_id' => $otherSite->getKey(),
            'type' => LocationType::Local,
        ])->create();

        Asset::factory()->create([
            'category_id' => $root->getKey(),
            'location_id' => $site->getKey(),
            'status' => AssetStatus::InService,
        ]);
        Asset::factory()->create([
            'category_id' => $subcategory->getKey(),
            'location_id' => $building->getKey(),
            'status' => AssetStatus::InService,
        ]);
        Asset::factory()->create([
            'category_id' => $subcategory->getKey(),
            'location_id' => $localUnderSite->getKey(),
            'status' => AssetStatus::Retired,
        ]);
        Asset::factory()->create([
            'category_id' => $root->getKey(),
            'location_id' => $localUnderBuilding->getKey(),
        ]);
        Asset::factory()->create([
            'category_id' => $root->getKey(),
            'location_id' => $otherLocal->getKey(),
        ]);
        Asset::factory()->create([
            'category_id' => $root->getKey(),
            'location_id' => null,
        ]);

        $statistics = app(DashboardStatistics::class);
        $distribution = $statistics->forUser($viewer, [])['sitesChart'];
        $counts = $distribution->mapWithKeys(fn (object $row): array => [
            $row->filter_value === null ? 'unlocated' : (string) $row->filter_value => (int) $row->total,
        ]);

        $this->assertSame(4, $counts->get((string) $site->getKey()));
        $this->assertSame(1, $counts->get((string) $otherSite->getKey()));
        $this->assertSame(1, $counts->get('unlocated'));
        $this->assertSame(6, $distribution->sum('total'));

        $filtered = $statistics->forUser($viewer, [
            'from' => now()->toDateString(),
            'to' => now()->toDateString(),
            'site_id' => $site->getKey(),
            'category_id' => $root->getKey(),
            'status' => AssetStatus::InService->value,
        ]);

        $this->assertSame(3, $filtered['metrics']['assets_total']);
        $this->assertSame(3, $filtered['sitesChart']->sum('total'));
        $this->assertSame($site->getKey(), (int) $filtered['sitesChart']->first()->filter_value);
    }

    public function test_super_admin_monthly_charts_use_saved_assets_assignments_and_movements(): void
    {
        $actor = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $asset = Asset::factory()->create();
        Asset::factory()->create(['created_at' => now()->subDays(2)]);
        $destination = Location::factory()->create();
        $service = Service::factory()->create();

        app(AssetAssignmentService::class)->assign(
            $asset,
            null,
            $service->getKey(),
            $actor,
            now()->subMinute()->toDateTimeString(),
            'Affectation réelle pour le tableau de bord',
            null,
        );
        app(AssetLocationService::class)->move(
            $asset,
            $destination,
            $actor,
            'Mouvement réel pour le tableau de bord',
        );

        $monthKey = now()->format('Y-m');

        $this->actingAs($actor)
            ->get(route('dashboard', [
                'from' => now()->toDateString(),
                'to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('data-month="'.$monthKey.'"', false)
            ->assertSee('data-month-assets="1"', false)
            ->assertSee('data-month-assignments="1"', false)
            ->assertSee('data-month-movements="1"', false)
            ->assertSee('Affectation réelle pour le tableau de bord')
            ->assertSee('Mouvement réel pour le tableau de bord');
    }
}
