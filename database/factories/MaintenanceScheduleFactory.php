<?php

namespace Database\Factories;

use App\Models\Machine;
use App\Models\MaintenanceSchedule;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceSchedule>
 */
class MaintenanceScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $scheduleType = $this->faker->randomElement(['hours', 'days', 'km']);

        return [
            'team_id' => Team::factory(),
            'machine_id' => Machine::factory(),
            'maintenance_type' => $this->faker->randomElement(['oil_change', 'tire_rotation', 'brake_inspection', 'full_service']),
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->sentence(),
            'schedule_type' => $scheduleType,
            'interval_hours' => $scheduleType === 'hours' ? $this->faker->numberBetween(250, 1000) : null,
            'interval_km' => $scheduleType === 'km' ? $this->faker->numberBetween(5000, 20000) : null,
            'interval_days' => $scheduleType === 'days' ? $this->faker->numberBetween(30, 180) : null,
            'last_service_hours' => null,
            'last_service_km' => null,
            'last_service_date' => $this->faker->dateTimeBetween('-3 months', '-1 week'),
            'next_service_hours' => null,
            'next_service_km' => null,
            'next_service_date' => $this->faker->dateTimeBetween('+1 week', '+3 months'),
            'priority' => $this->faker->randomElement(['low', 'medium', 'high', 'critical']),
            'status' => 'active',
            'estimated_cost' => $this->faker->randomFloat(2, 500, 10000),
            'estimated_duration_hours' => $this->faker->randomFloat(1, 1, 12),
        ];
    }

    public function overdue(): static
    {
        return $this->state([
            'next_service_date' => now()->subDays(7),
            'status' => 'active',
        ]);
    }
}
