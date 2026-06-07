<?php

namespace Database\Factories;

use App\Models\Machine;
use App\Models\MaintenanceAlert;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceAlert>
 */
class MaintenanceAlertFactory extends Factory
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
            'machine_id' => Machine::factory(),
            'maintenance_schedule_id' => null,
            'alert_type' => $this->faker->randomElement(['overdue', 'upcoming', 'critical']),
            'title' => $this->faker->sentence(5),
            'message' => $this->faker->sentence(),
            'severity' => $this->faker->randomElement(['low', 'medium', 'high', 'critical']),
            'status' => 'active',
            'triggered_at' => now(),
            'acknowledged_at' => null,
            'acknowledged_by' => null,
            'resolved_at' => null,
            'resolved_by' => null,
            'notes' => null,
        ];
    }

    public function resolved(): static
    {
        return $this->state([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);
    }
}
