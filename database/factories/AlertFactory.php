<?php

namespace Database\Factories;

use App\Models\Alert;
use App\Models\Machine;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alert>
 */
class AlertFactory extends Factory
{
    protected $model = Alert::class;

    /**
     * @return array<mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'machine_id' => Machine::factory(),
            'type' => $this->faker->randomElement(['engine', 'fuel', 'maintenance', 'geofence', 'temperature', 'pressure', 'vibration']),
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'priority' => $this->faker->randomElement(['critical', 'high', 'medium', 'low']),
            'status' => 'active',
            'triggered_at' => now(),
            'acknowledged_at' => null,
            'resolved_at' => null,
            'acknowledged_by' => null,
            'resolved_by' => null,
            'metadata' => [
                'source' => $this->faker->randomElement(['rule_engine', 'sensor', 'manual']),
                'value' => $this->faker->randomFloat(2, 0, 100),
            ],
        ];
    }

    /**
     * Indicate that the alert is acknowledged.
     */
    public function acknowledged(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'acknowledged',
                'acknowledged_at' => now(),
                'acknowledged_by' => User::factory(),
            ];
        });
    }

    /**
     * Indicate that the alert is resolved.
     */
    public function resolved(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'resolved',
                'acknowledged_at' => now()->subHours(2),
                'acknowledged_by' => User::factory(),
                'resolved_at' => now(),
                'resolved_by' => User::factory(),
            ];
        });
    }

    /**
     * Indicate that the alert is critical.
     */
    public function critical(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'critical',
        ]);
    }

    /**
     * Indicate that the alert is high priority.
     */
    public function high(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'high',
        ]);
    }
}
