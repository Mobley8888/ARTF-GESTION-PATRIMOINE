<?php

namespace Database\Factories;

use App\Enums\LocationType;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'parent_id' => null,
            'type' => LocationType::Site,
            'code' => fake()->unique()->bothify('LOC-#####'),
            'name' => fake()->unique()->words(2, true),
            'address' => fake()->streetAddress(),
            'surface_m2' => null,
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }

    public function buildingUnder(Location $site): static
    {
        return $this->state(fn (): array => [
            'parent_id' => $site->getKey(),
            'type' => LocationType::Building,
        ]);
    }

    public function roomUnder(Location $building): static
    {
        return $this->state(fn (): array => [
            'parent_id' => $building->getKey(),
            'type' => LocationType::Room,
        ]);
    }
}
