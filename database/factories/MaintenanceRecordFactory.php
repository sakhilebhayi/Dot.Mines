<?php

namespace Database\Factories;

use App\Models\Machine;
use App\Models\MaintenanceRecord;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceRecord>
 */
class MaintenanceRecordFactory extends Factory
{
    protected $model = MaintenanceRecord::class;

    /**
     * @return array<mixed>
     */
    public function definition(): array
    {
        $scheduledDate = $this->faker->dateTimeBetween('-30 days', '+30 days');
        $status = $this->faker->randomElement(['scheduled', 'in_progress', 'completed', 'cancelled']);

        // Ensure completed records have a past scheduled date to keep completed_at valid
        if ($status === 'completed') {
            $scheduledDate = $this->faker->dateTimeBetween('-30 days', '-1 day');
        }

        return [
            'team_id' => Team::factory(),
            'machine_id' => Machine::factory(),
            'maintenance_schedule_id' => null,
            'maintenance_type' => $this->faker->randomElement(['preventive', 'corrective', 'inspection', 'repair', 'replacement']),
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'work_performed' => $status === 'completed' ? $this->faker->paragraph() : null,
            'status' => $status,
            'priority' => $this->faker->randomElement(['low', 'medium', 'high', 'critical']),
            'scheduled_date' => $scheduledDate,
            'started_at' => $status !== 'scheduled' ? $scheduledDate : null,
            'completed_at' => $status === 'completed' ? $this->faker->dateTimeBetween($scheduledDate, 'now') : null,
            'assigned_to' => User::factory(),
            'completed_by' => $status === 'completed' ? User::factory() : null,
            'labor_hours' => $status === 'completed' ? $this->faker->randomFloat(2, 1, 24) : null,
            'labor_cost' => $status === 'completed' ? $this->faker->randomFloat(2, 500, 5000) : null,
            'parts_cost' => $status === 'completed' ? $this->faker->randomFloat(2, 0, 10000) : null,
            'total_cost' => function (array $attributes) {
                return $attributes['status'] === 'completed'
                    ? ($attributes['labor_cost'] ?? 0) + ($attributes['parts_cost'] ?? 0)
                    : null;
            },
            'parts_used' => $status === 'completed' ? [
                ['part_number' => $this->faker->bothify('PART-####'), 'quantity' => $this->faker->numberBetween(1, 10), 'cost' => $this->faker->randomFloat(2, 50, 1000)],
            ] : null,
            'fault_codes_cleared' => $this->faker->optional()->randomElements(['P0301', 'P0420', 'P0171'], 2),
            'odometer_reading' => $this->faker->optional()->randomFloat(2, 10000, 100000),
            'hour_meter_reading' => $this->faker->optional()->randomFloat(2, 1000, 10000),
            'technician_notes' => $this->faker->optional()->paragraph(),
            'machine_operational' => $status === 'completed' ? true : false,
        ];
    }

    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $scheduledDate = $this->faker->dateTimeBetween('-30 days', '-3 days');
            $startedAt = $this->faker->dateTimeBetween($scheduledDate, '-1 day');
            $completedAt = $this->faker->dateTimeBetween($startedAt, 'now');

            return [
                'scheduled_date' => $scheduledDate,
                'status' => 'completed',
                'started_at' => $startedAt,
                'completed_at' => $completedAt,
                'completed_by' => User::factory(),
                'work_performed' => $this->faker->paragraph(),
                'labor_hours' => $this->faker->randomFloat(2, 1, 24),
                'labor_cost' => $this->faker->randomFloat(2, 500, 5000),
                'parts_cost' => $this->faker->randomFloat(2, 0, 10000),
                'machine_operational' => true,
            ];
        });
    }

    public function critical(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'priority' => 'critical',
                'maintenance_type' => 'corrective',
            ];
        });
    }

    public function scheduled(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'scheduled',
                'started_at' => null,
                'completed_at' => null,
                'completed_by' => null,
                'work_performed' => null,
                'labor_hours' => null,
                'labor_cost' => null,
                'parts_cost' => null,
                'total_cost' => null,
                'machine_operational' => false,
            ];
        });
    }
}
