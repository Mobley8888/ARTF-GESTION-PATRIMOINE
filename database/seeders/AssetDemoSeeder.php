<?php

namespace Database\Seeders;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\LocationType;
use App\Models\Agent;
use App\Models\Asset;
use App\Models\AssetLocationHistory;
use App\Models\Category;
use App\Models\Direction;
use App\Models\Location;
use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AssetDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $category = Category::query()->firstOrCreate(
                ['code' => 'DEMO-CAT-IT'],
                ['name' => 'Matériel informatique fictif', 'is_active' => true],
            );

            $subcategory = Category::query()->firstOrCreate(
                ['code' => 'DEMO-CAT-IT-PORT'],
                [
                    'parent_id' => $category->getKey(),
                    'name' => 'Équipements portables fictifs',
                    'is_active' => true,
                ],
            );

            $site = Location::query()->firstOrCreate(
                ['code' => 'DEMO-SITE'],
                [
                    'type' => LocationType::Site,
                    'name' => 'Site de démonstration fictif',
                    'is_active' => true,
                ],
            );

            $building = Location::query()->firstOrCreate(
                ['code' => 'DEMO-BUILDING'],
                [
                    'parent_id' => $site->getKey(),
                    'type' => LocationType::Building,
                    'name' => 'Bâtiment de démonstration fictif',
                    'is_active' => true,
                ],
            );

            $room = Location::query()->firstOrCreate(
                ['code' => 'DEMO-ROOM'],
                [
                    'parent_id' => $building->getKey(),
                    'type' => LocationType::Room,
                    'name' => 'Local de démonstration fictif',
                    'is_active' => true,
                ],
            );

            $direction = Direction::query()->firstOrCreate(
                ['code' => 'DEMO-DIRECTION'],
                ['name' => 'Direction de démonstration fictive', 'is_active' => true],
            );

            $service = Service::query()->firstOrCreate(
                ['direction_id' => $direction->getKey(), 'code' => 'DEMO-SERVICE'],
                ['name' => 'Service de démonstration fictif', 'is_active' => true],
            );

            Agent::query()->firstOrCreate(
                ['employee_code' => 'DEMO-AGENT-001'],
                [
                    'service_id' => $service->getKey(),
                    'first_name' => 'Agent',
                    'last_name' => 'Fictif',
                    'is_active' => true,
                ],
            );

            $assets = [
                [
                    'asset_code' => 'DEMO-ASSET-001',
                    'designation' => 'Équipement informatique fictif',
                    'acquisition_value' => '250000.00',
                ],
                [
                    'asset_code' => 'DEMO-ASSET-002',
                    'designation' => 'Matériel portable fictif',
                    'acquisition_value' => '180000.00',
                ],
            ];

            foreach ($assets as $attributes) {
                $asset = Asset::query()->firstOrCreate(
                    ['asset_code' => $attributes['asset_code']],
                    [
                        ...$attributes,
                        'category_id' => $subcategory->getKey(),
                        'location_id' => $room->getKey(),
                        'acquired_on' => '2025-01-15',
                        'condition' => AssetCondition::Good,
                        'status' => AssetStatus::InService,
                        'characteristics' => ['demo' => true],
                    ],
                );

                if (! $asset->locationHistory()->exists() && $asset->location_id !== null) {
                    AssetLocationHistory::query()->create([
                        'asset_id' => $asset->getKey(),
                        'from_location_id' => null,
                        'to_location_id' => $asset->location_id,
                        'recorded_by' => null,
                        'moved_at' => $asset->created_at,
                        'reason' => 'Initialisation des données de démonstration fictives',
                    ]);
                }
            }
        });
    }
}
