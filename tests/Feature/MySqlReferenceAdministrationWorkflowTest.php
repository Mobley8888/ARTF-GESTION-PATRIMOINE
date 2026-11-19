<?php

namespace Tests\Feature;

use App\Enums\LocationType;
use App\Enums\UserRole;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MySqlReferenceAdministrationWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Ce scénario doit être exécuté sur MySQL.');
        }
    }

    public function test_mysql_referentials_can_feed_asset_creation_without_persistent_test_data(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::AssetManager]));
        $suffix = Str::upper(Str::random(12));

        $this->post(route('references.categories.store'), [
            'code' => 'ROOT-'.$suffix,
            'name' => 'Catégorie de validation',
        ])->assertRedirect();
        $root = Category::query()->where('code', 'ROOT-'.$suffix)->firstOrFail();

        $this->post(route('references.categories.store'), [
            'parent_id' => $root->getKey(),
            'code' => 'SUB-'.$suffix,
            'name' => 'Sous-catégorie de validation',
        ])->assertRedirect();
        $subcategory = Category::query()->where('code', 'SUB-'.$suffix)->firstOrFail();
        $this->get(route('references.categories.index', ['active' => 'active', 'q' => 'ROOT-'.$suffix]))
            ->assertOk()
            ->assertSee($root->name);
        $this->get(route('references.categories.show', $subcategory))
            ->assertOk()
            ->assertSee($subcategory->name);
        $this->put(route('references.categories.update', $subcategory), [
            'parent_id' => $root->getKey(),
            'code' => 'SUB-'.$suffix,
            'name' => 'Sous-catégorie modifiée',
        ])->assertRedirect(route('references.categories.show', $subcategory));
        $subcategory->refresh();

        $this->post(route('references.locations.store'), [
            'type' => LocationType::Site->value,
            'code' => 'SITE-'.$suffix,
            'name' => 'Site de validation',
        ])->assertRedirect();
        $site = Location::query()->where('code', 'SITE-'.$suffix)->firstOrFail();

        $this->post(route('references.locations.store'), [
            'type' => LocationType::Batiment->value,
            'parent_id' => $site->getKey(),
            'code' => 'BLD-'.$suffix,
            'name' => 'Bâtiment de validation',
        ])->assertRedirect();
        $building = Location::query()->where('code', 'BLD-'.$suffix)->firstOrFail();

        $this->post(route('references.locations.store'), [
            'type' => LocationType::Local->value,
            'parent_id' => $building->getKey(),
            'code' => 'ROOM-'.$suffix,
            'name' => 'Local de validation',
        ])->assertRedirect();
        $room = Location::query()->where('code', 'ROOM-'.$suffix)->firstOrFail();
        $this->get(route('references.locations.index', ['type' => LocationType::Local->value]))
            ->assertOk()
            ->assertSee($room->name);
        $this->get(route('references.locations.show', $room))
            ->assertOk()
            ->assertSee($room->code);
        $this->put(route('references.locations.update', $room), [
            'type' => LocationType::Local->value,
            'parent_id' => $building->getKey(),
            'code' => 'ROOM-'.$suffix,
            'name' => 'Local modifié',
        ])->assertRedirect(route('references.locations.show', $room));
        $room->refresh();

        $assetCode = 'ASSET-'.$suffix;
        $this->get(route('assets.create'))
            ->assertOk()
            ->assertSee('Sous-catégorie modifiée')
            ->assertSee('Site de validation');
        $this->post(route('assets.store'), [
            'asset_code' => $assetCode,
            'designation' => 'Bien temporaire de test',
            'category_id' => $subcategory->getKey(),
            'site_id' => $site->getKey(),
            'building_id' => $building->getKey(),
            'room_id' => $room->getKey(),
            'status' => 'in_service',
        ])->assertRedirect();

        $asset = Asset::query()->where('asset_code', $assetCode)->firstOrFail();
        $this->assertSame($subcategory->getKey(), $asset->category_id);
        $this->assertSame($room->getKey(), $asset->location_id);
        $this->assertDatabaseHas('asset_location_histories', [
            'asset_id' => $asset->getKey(),
            'to_location_id' => $room->getKey(),
        ]);
        $this->patch(route('references.locations.status', $room))->assertSessionHasErrors('location');
        $this->patch(route('references.categories.status', $subcategory))->assertSessionHasErrors('category');
    }
}
