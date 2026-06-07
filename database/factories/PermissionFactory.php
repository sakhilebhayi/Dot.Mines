<?php

namespace Database\Factories;

use App\Models\Permission;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->slug(2);

        return [
            'team_id' => Team::factory(),
            'name' => $name,
            'display_name' => ucwords(str_replace('-', ' ', $name)),
            'description' => $this->faker->sentence(),
            'group' => $this->faker->randomElement(['fleet', 'fuel', 'maintenance', 'alerts', 'reports']),
        ];
    }
}
