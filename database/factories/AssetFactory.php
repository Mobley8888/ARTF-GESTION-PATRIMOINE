<?php

namespace Database\Factories;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'location_id' => Location::factory(),
            'created_by' => null,
            'updated_by' => null,
            'asset_code' => fake()->unique()->bothify('BIEN-#####'),
            'designation' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'serial_number' => fake()->bothify('SN-########'),
            'characteristics' => ['source' => 'test'],
            'acquired_on' => fake()->date(),
            'acquisition_value' => fake()->randomFloat(2, 1000, 10000000),
            'condition' => AssetCondition::Good,
            'status' => AssetStatus::InService,
        ];
    }
}
