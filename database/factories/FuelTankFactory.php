<?php

namespace Database\Factories;

use App\Models\FuelTank;
use App\Models\MineArea;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FuelTank>
 */
class FuelTankFactory extends Factory
{
    protected $model = FuelTank::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'mine_area_id' => MineArea::factory(),
            'name' => 'Tank '.$this->faker->unique()->numberBetween(1, 100),
            'tank_number' => $this->faker->unique()->bothify('TANK-###'),
            'location_description' => $this->faker->sentence(3),
            'location_latitude' => $this->faker->latitude(),
            'location_longitude' => $this->faker->longitude(),
            'capacity_liters' => $this->faker->randomFloat(2, 5000, 50000),
            'current_level_liters' => function (array $attributes) {
                return $this->faker->randomFloat(2, 1000, $attributes['capacity_liters'] * 0.9);
            },
            'minimum_level_liters' => function (array $attributes) {
                return $attributes['capacity_liters'] * 0.2;
            },
            'fuel_type' => $this->faker->randomElement(['diesel', 'petrol', 'biodiesel']),
            'status' => $this->faker->randomElement(['active', 'maintenance', 'inactive']),
            'last_inspection_date' => $this->faker->dateTimeBetween('-3 months', 'now'),
            'next_inspection_date' => $this->faker->dateTimeBetween('now', '+3 months'),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    public function lowLevel(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'current_level_liters' => $attributes['minimum_level_liters'] * 0.8,
            ];
        });
    }
}
