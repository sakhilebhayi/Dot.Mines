<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Notification::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = ['sensor_reading', 'maintenance_alert', 'compliance_violation', 'production_anomaly', 'sensor_status_changed'];
        $levels = ['info', 'warning', 'high', 'critical'];

        return [
            'team_id' => Team::factory(),
            'type' => $this->faker->randomElement($types),
            'title' => $this->faker->sentence(),
            'message' => $this->faker->paragraph(),
            'alert_level' => $this->faker->randomElement($levels),
            'data' => [
                'source' => 'sensor_123',
                'timestamp' => now()->toIso8601String(),
            ],
            'action_url' => $this->faker->url(),
            'is_read' => false,
            'read_at' => null,
        ];
    }

    /**
     * Mark notification as read
     */
    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    /**
     * Set notification as critical
     */
    public function critical(): static
    {
        return $this->state(fn (array $attributes) => [
            'alert_level' => 'critical',
        ]);
    }

    /**
     * Set notification as warning
     */
    public function warning(): static
    {
        return $this->state(fn (array $attributes) => [
            'alert_level' => 'warning',
        ]);
    }
}
