<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->randomElement(['admin', 'fleet_manager', 'operator', 'viewer', 'maintenance_tech']);

        return [
            'team_id' => Team::factory(),
            'name' => $name,
            'display_name' => ucwords(str_replace('_', ' ', $name)),
            'description' => $this->faker->sentence(),
        ];
    }
}
