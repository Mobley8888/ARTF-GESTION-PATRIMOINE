<?php

namespace Database\Factories;

use App\Models\Direction;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'direction_id' => Direction::factory(),
            'code' => fake()->unique()->bothify('SRV-#####'),
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
