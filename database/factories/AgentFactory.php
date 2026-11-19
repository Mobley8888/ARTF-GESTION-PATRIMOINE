<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Agent>
 */
class AgentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'user_id' => null,
            'employee_code' => fake()->unique()->bothify('AG-#####'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'is_active' => true,
        ];
    }
}
