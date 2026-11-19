<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\LocationType;
use App\Models\Agent;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Direction;
use App\Models\Location;
use App\Models\Service;
use App\Models\User;
use App\Services\AssetLocationService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class PatrimonialDataModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_categories_are_limited_to_two_levels(): void
    {
        $category = Category::factory()->create();
        $subcategory = Category::factory()->subcategoryOf($category)->create();

        $this->assertTrue($subcategory->parent->is($category));

        $this->expectException(ValidationException::class);
        Category::factory()->subcategoryOf($subcategory)->create();
    }

    public function test_deactivated_category_codes_cannot_be_reused(): void
    {
        $category = Category::factory()->create(['code' => 'CAT-CONSERVED']);
        $category->update(['is_active' => false]);

        $this->expectException(QueryException::class);
        Category::factory()->create(['code' => 'CAT-CONSERVED']);
    }

    public function test_locations_support_site_building_room_and_direct_site_local_paths(): void
    {
        $site = Location::factory()->create();
        $building = Location::factory()->buildingUnder($site)->create();
        $room = Location::factory()->roomUnder($building)->create();
        $directLocal = Location::factory()->create([
            'parent_id' => $site->getKey(),
            'type' => LocationType::Local,
        ]);

        $this->assertSame(LocationType::Site, $site->type);
        $this->assertTrue($room->parent->is($building));
        $this->assertTrue($building->parent->is($site));
        $this->assertTrue($directLocal->parent->is($site));

        $this->expectException(ValidationException::class);
        Location::factory()->create([
            'parent_id' => $room->getKey(),
            'type' => LocationType::Local,
        ]);
    }

    public function test_assets_have_related_category_location_and_unique_codes(): void
    {
        $asset = Asset::factory()->create();

        $this->assertTrue($asset->category->exists);
        $this->assertTrue($asset->location->exists);
        $this->assertSame(AssetStatus::InService, $asset->status);

        $this->expectException(QueryException::class);
        Asset::factory()->create(['asset_code' => $asset->asset_code]);
    }

    public function test_agent_is_related_to_service_direction_and_optional_user(): void
    {
        $direction = Direction::factory()->create();
        $service = Service::factory()->create(['direction_id' => $direction->getKey()]);
        $user = User::factory()->create();
        $agent = Agent::factory()->create([
            'service_id' => $service->getKey(),
            'user_id' => $user->getKey(),
        ]);

        $this->assertTrue($agent->service->direction->is($direction));
        $this->assertTrue($agent->user->is($user));
        $this->assertTrue($user->agent->is($agent));
    }

    public function test_asset_location_move_is_transactional_and_audited(): void
    {
        $site = Location::factory()->create();
        $building = Location::factory()->buildingUnder($site)->create();
        $room = Location::factory()->roomUnder($building)->create();
        $asset = Asset::factory()->create(['location_id' => $site->getKey()]);
        $user = User::factory()->create();
        $service = app(AssetLocationService::class);

        $event = $service->move($asset, $room, $user, 'Transfert de démonstration');

        $this->assertNotNull($event);
        $this->assertSame($site->getKey(), $event->from_location_id);
        $this->assertSame($room->getKey(), $event->to_location_id);
        $this->assertSame($user->getKey(), $event->recorded_by);
        $this->assertSame($room->getKey(), $asset->fresh()->location_id);
        $this->assertNull($service->move($asset->fresh(), $room, $user, 'Aucun changement'));
        $this->assertCount(1, $asset->locationHistory);

        try {
            $event->update(['reason' => 'Modification interdite']);
            $this->fail('L’historique ne doit pas être modifiable.');
        } catch (LogicException) {
            $this->assertDatabaseHas('asset_location_histories', [
                'id' => $event->getKey(),
                'reason' => 'Transfert de démonstration',
            ]);
        }

        $this->expectException(LogicException::class);
        $event->delete();
    }

    public function test_negative_acquisition_value_is_rejected(): void
    {
        $asset = Asset::factory()->create();
        $asset->acquisition_value = '-0.01';

        $this->expectException(ValidationException::class);
        $asset->save();
    }

    public function test_acquisition_value_must_fit_decimal_precision(): void
    {
        $asset = Asset::factory()->create();
        $asset->acquisition_value = '123.456';

        $this->expectException(ValidationException::class);
        $asset->save();
    }

    public function test_demo_seeder_is_fictional_and_idempotent(): void
    {
        $this->seed();
        $this->seed();

        $this->assertDatabaseCount('assets', 2);
        $this->assertDatabaseCount('asset_location_histories', 2);
        $this->assertDatabaseCount('users', 0);
    }
}
