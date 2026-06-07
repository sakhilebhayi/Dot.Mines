<?php

namespace Database\Factories;

use App\Models\FuelAlert;
use App\Models\FuelTank;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FuelAlert>
 */
class FuelAlertFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'fuel_tank_id' => FuelTank::factory(),
            'machine_id' => null,
            'alert_type' => $this->faker->randomElement(['low_level', 'critical_level', 'over_consumption', 'budget_exceeded']),
            'title' => $this->faker->sentence(4),
            'message' => $this->faker->sentence(),
            'severity' => $this->faker->randomElement(['low', 'medium', 'high', 'critical']),
            'status' => 'active',
            'triggered_at' => now(),
            'acknowledged_at' => null,
            'resolved_at' => null,
            'acknowledged_by' => null,
            'resolved_by' => null,
            'metadata' => null,
        ];
    }
}
