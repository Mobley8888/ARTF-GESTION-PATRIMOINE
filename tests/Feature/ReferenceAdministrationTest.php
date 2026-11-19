<?php

namespace Tests\Feature;

use App\Enums\LocationType;
use App\Enums\UserRole;
use App\Models\Asset;
use App\Models\AssetLocationHistory;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReferenceAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_asset_manager_can_manage_categories_and_deactivation_preserves_used_references(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::AssetManager]));
        $rootCode = 'ROOT-'.Str::upper(Str::random(8));
        $childCode = 'SUB-'.Str::upper(Str::random(8));

        $this->get(route('references.categories.index'))->assertOk();
        $this->post(route('references.categories.store'), [
            'code' => $rootCode,
            'name' => 'Équipements informatiques',
            'description' => 'Catégorie de test',
        ])->assertRedirect();
        $root = Category::query()->where('code', $rootCode)->firstOrFail();

        $this->post(route('references.categories.store'), [
            'parent_id' => $root->getKey(),
            'code' => $childCode,
            'name' => 'Ordinateurs portables',
        ])->assertRedirect();
        $subcategory = Category::query()->where('code', $childCode)->firstOrFail();

        $this->get(route('references.categories.show', $subcategory))
            ->assertOk()
            ->assertSee($root->name)
            ->assertSee('Ordinateurs portables');
        $this->put(route('references.categories.update', $subcategory), [
            'parent_id' => $root->getKey(),
            'code' => $childCode,
            'name' => 'Terminaux portables',
            'description' => 'Désignation modifiée',
        ])->assertRedirect(route('references.categories.show', $subcategory));

        $this->from(route('references.categories.create'))
            ->post(route('references.categories.store'), [
                'parent_id' => $subcategory->getKey(),
                'code' => 'NEST-'.Str::upper(Str::random(8)),
                'name' => 'Niveau interdit',
            ])
            ->assertRedirect(route('references.categories.create'))
            ->assertSessionHasErrors('parent_id');

        $this->patch(route('references.categories.status', $root))->assertSessionHasErrors('category');
        $this->patch(route('references.categories.status', $subcategory))->assertRedirect();
        $this->patch(route('references.categories.status', $root))->assertRedirect();
        $this->patch(route('references.categories.status', $subcategory))->assertSessionHasErrors('parent_id');
        $this->patch(route('references.categories.status', $root))->assertRedirect();
        $this->patch(route('references.categories.status', $subcategory))->assertRedirect();

        $root->refresh();
        $subcategory->refresh();
        $this->assertTrue($root->is_active);
        $this->assertTrue($subcategory->is_active);
        $this->assertFalse(Route::has('references.categories.destroy'));
    }

    public function test_location_hierarchy_filters_asset_form_and_records_the_selected_local(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::AssetManager]));
        $category = Category::factory()->create();
        $subcategory = Category::factory()->subcategoryOf($category)->create();
        $siteCode = 'SITE-'.Str::upper(Str::random(8));
        $otherSiteCode = 'SITE-'.Str::upper(Str::random(8));

        $this->post(route('references.locations.store'), [
            'type' => LocationType::Site->value,
            'code' => $siteCode,
            'name' => 'Site administratif',
            'address' => 'Adresse de test',
        ])->assertRedirect();
        $site = Location::query()->where('code', $siteCode)->firstOrFail();

        $buildingCode = 'BLD-'.Str::upper(Str::random(8));
        $this->post(route('references.locations.store'), [
            'type' => LocationType::Batiment->value,
            'parent_id' => $site->getKey(),
            'code' => $buildingCode,
            'name' => 'Bâtiment principal',
        ])->assertRedirect();
        $building = Location::query()->where('code', $buildingCode)->firstOrFail();

        $roomCode = 'ROOM-'.Str::upper(Str::random(8));
        $this->post(route('references.locations.store'), [
            'type' => LocationType::Local->value,
            'parent_id' => $building->getKey(),
            'code' => $roomCode,
            'name' => 'Bureau 101',
        ])->assertRedirect();
        $room = Location::query()->where('code', $roomCode)->firstOrFail();
        $directLocalCode = 'LOC-'.Str::upper(Str::random(8));
        $this->post(route('references.locations.store'), [
            'type' => LocationType::Local->value,
            'parent_id' => $site->getKey(),
            'code' => $directLocalCode,
            'name' => 'Direction départementale',
        ])->assertRedirect();
        $directLocal = Location::query()->where('code', $directLocalCode)->firstOrFail();
        $this->put(route('references.locations.update', $directLocal), [
            'type' => LocationType::Local->value,
            'parent_id' => $site->getKey(),
            'code' => $directLocalCode,
            'name' => 'Direction départementale modifiée',
        ])->assertRedirect(route('references.locations.show', $directLocal));

        $this->post(route('references.locations.store'), [
            'type' => LocationType::Site->value,
            'code' => $otherSiteCode,
            'name' => 'Autre site',
        ])->assertRedirect();
        $otherSite = Location::query()->where('code', $otherSiteCode)->firstOrFail();
        $this->post(route('references.locations.store'), [
            'type' => LocationType::Building->value,
            'parent_id' => $otherSite->getKey(),
            'code' => 'BLD-'.Str::upper(Str::random(8)),
            'name' => 'Bâtiment annexe',
        ])->assertRedirect();
        $otherBuilding = Location::query()->where('parent_id', $otherSite->getKey())->firstOrFail();

        $this->from(route('references.locations.create'))
            ->post(route('references.locations.store'), [
                'type' => LocationType::Local->value,
                'parent_id' => $room->getKey(),
                'code' => 'BAD-'.Str::upper(Str::random(8)),
                'name' => 'Parent de type local interdit',
            ])
            ->assertRedirect(route('references.locations.create'))
            ->assertSessionHasErrors('parent_id');

        $this->patch(route('references.locations.status', $room))->assertRedirect();
        $this->patch(route('references.locations.status', $directLocal))->assertRedirect();
        $this->patch(route('references.locations.status', $building))->assertRedirect();
        $this->patch(route('references.locations.status', $site))->assertRedirect();
        $this->patch(route('references.locations.status', $building))->assertSessionHasErrors('parent_id');
        $this->patch(route('references.locations.status', $site))->assertRedirect();
        $this->patch(route('references.locations.status', $building))->assertRedirect();
        $this->patch(route('references.locations.status', $room))->assertRedirect();
        $this->patch(route('references.locations.status', $directLocal))->assertRedirect();

        $this->get(route('references.locations.index', ['type' => LocationType::Building->value, 'active' => 'active']))
            ->assertOk()
            ->assertSee('Bâtiment principal')
            ->assertSee('Bâtiment annexe');
        $this->get(route('assets.create'))
            ->assertOk()
            ->assertSee($subcategory->name)
            ->assertSee('Site administratif')
            ->assertSee('data-parent-category="'.$category->getKey().'"', false)
            ->assertSee('data-parent="'.$site->getKey().'"', false)
            ->assertSee('data-parent="'.$building->getKey().'"', false);

        $badCode = 'ASSET-'.Str::upper(Str::random(8));
        $this->from(route('assets.create'))->post(route('assets.store'), [
            'asset_code' => $badCode,
            'designation' => 'Bien de test',
            'category_id' => $subcategory->getKey(),
            'site_id' => $site->getKey(),
            'building_id' => $otherBuilding->getKey(),
            'room_id' => '',
            'status' => 'in_service',
        ])->assertRedirect(route('assets.create'))->assertSessionHasErrors('building_id');
        $this->assertDatabaseMissing('assets', ['asset_code' => $badCode]);

        $assetCode = 'ASSET-'.Str::upper(Str::random(8));
        $this->post(route('assets.store'), [
            'asset_code' => $assetCode,
            'designation' => 'Bien de test',
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

        $directAssetCode = 'ASSET-'.Str::upper(Str::random(8));
        $this->post(route('assets.store'), [
            'asset_code' => $directAssetCode,
            'designation' => 'Bien localisé directement sur le site',
            'category_id' => $subcategory->getKey(),
            'site_id' => $site->getKey(),
            'building_id' => '',
            'room_id' => $directLocal->getKey(),
            'status' => 'in_service',
        ])->assertRedirect();
        $directAsset = Asset::query()->where('asset_code', $directAssetCode)->firstOrFail();
        $this->assertSame($directLocal->getKey(), $directAsset->location_id);

        $this->patch(route('references.locations.status', $room))->assertSessionHasErrors('location');
        $this->patch(route('references.categories.status', $subcategory))->assertSessionHasErrors('category');
        $this->assertFalse(Route::has('references.locations.destroy'));
    }

    public function test_category_selector_maps_the_selected_subcategory_and_rejects_another_parent(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::AssetManager]));
        $category = Category::factory()->create();
        $subcategory = Category::factory()->subcategoryOf($category)->create();
        $otherCategory = Category::factory()->create();
        $otherSubcategory = Category::factory()->subcategoryOf($otherCategory)->create();

        $this->get(route('assets.create'))
            ->assertOk()
            ->assertSee('id="category_parent_id"', false)
            ->assertSee('id="subcategory_id"', false)
            ->assertSee('data-parent-category="'.$category->getKey().'"', false)
            ->assertSee('data-parent-category="'.$otherCategory->getKey().'"', false);

        $invalidCode = 'ASSET-'.Str::upper(Str::random(8));
        $this->from(route('assets.create'))->post(route('assets.store'), [
            'asset_code' => $invalidCode,
            'designation' => 'Sous-catégorie incompatible',
            'category_parent_id' => $category->getKey(),
            'subcategory_id' => $otherSubcategory->getKey(),
            'status' => 'in_service',
        ])->assertRedirect(route('assets.create'))
            ->assertSessionHasErrors('subcategory_id');
        $this->assertDatabaseMissing('assets', ['asset_code' => $invalidCode]);

        $assetCode = 'ASSET-'.Str::upper(Str::random(8));
        $this->post(route('assets.store'), [
            'asset_code' => $assetCode,
            'designation' => 'Sous-catégorie valide',
            'category_parent_id' => $category->getKey(),
            'subcategory_id' => $subcategory->getKey(),
            'status' => 'in_service',
        ])->assertRedirect();
        $asset = Asset::query()->where('asset_code', $assetCode)->firstOrFail();

        $this->assertSame($subcategory->getKey(), $asset->category_id);
        $this->get(route('assets.edit', $asset))
            ->assertOk()
            ->assertSee('value="'.$category->getKey().'" selected', false)
            ->assertSee('value="'.$subcategory->getKey().'" data-parent-category="'.$category->getKey().'" selected', false);

        $this->put(route('assets.update', $asset), [
            'asset_code' => $assetCode,
            'designation' => 'Sous-catégorie modifiée',
            'category_parent_id' => $category->getKey(),
            'subcategory_id' => $subcategory->getKey(),
            'status' => 'in_service',
        ])->assertRedirect(route('assets.show', $asset));
        $this->assertSame($subcategory->getKey(), $asset->fresh()->category_id);
    }

    public function test_location_used_only_in_location_history_cannot_be_deactivated(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::AssetManager]));
        $site = Location::factory()->create();
        $building = Location::factory()->buildingUnder($site)->create();
        $source = Location::factory()->roomUnder($building)->create();
        $destination = Location::factory()->roomUnder($building)->create();
        $currentLocation = Location::factory()->roomUnder($building)->create();
        $asset = Asset::factory()->create(['location_id' => $currentLocation->getKey()]);

        AssetLocationHistory::query()->create([
            'asset_id' => $asset->getKey(),
            'from_location_id' => $source->getKey(),
            'to_location_id' => $destination->getKey(),
            'moved_at' => now(),
            'reason' => 'Mouvement conservé pour le test',
        ]);

        $this->patch(route('references.locations.status', $source))
            ->assertSessionHasErrors('location');
        $this->patch(route('references.locations.status', $destination))
            ->assertSessionHasErrors('location');
        $this->assertTrue($source->fresh()->is_active);
        $this->assertTrue($destination->fresh()->is_active);
    }

    public function test_viewer_cannot_access_reference_administration_directly(): void
    {
        $category = Category::factory()->create();
        $this->actingAs(User::factory()->create(['role' => UserRole::Viewer]));

        $this->get(route('references.categories.index'))->assertForbidden();
        $this->get(route('references.locations.index'))->assertForbidden();
        $this->post(route('references.categories.store'))->assertForbidden();
        $this->patch(route('references.locations.status', Location::factory()->create()))->assertForbidden();

        $this->assertTrue($category->is_active);
    }

    public function test_asset_request_rejects_a_subcategory_whose_root_is_inactive(): void
    {
        $category = Category::factory()->create();
        $subcategory = Category::factory()->subcategoryOf($category)->create();
        DB::table('categories')->where('id', $category->getKey())->update(['is_active' => false]);
        $this->actingAs(User::factory()->create(['role' => UserRole::AssetManager]));

        $this->post(route('assets.store'), [
            'asset_code' => 'INACTIVE-PARENT-001',
            'designation' => 'Bien de test',
            'category_id' => $subcategory->getKey(),
            'status' => 'in_service',
        ])->assertSessionHasErrors('category_id');

        $this->assertDatabaseCount('assets', 0);
    }
}
