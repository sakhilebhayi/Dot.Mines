<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => $this->faker->unique()->word(),
            'display_name' => $this->faker->words(2, true),
            'description' => $this->faker->sentence(),
        ];
    }
}
