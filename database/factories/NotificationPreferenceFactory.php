<?php

namespace Database\Factories;

use App\Models\NotificationPreference;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationPreference>
 */
class NotificationPreferenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'team_id' => Team::factory(),
            'notification_type' => $this->faker->randomElement(['alert', 'maintenance', 'fuel', 'ai_drift', 'geofence']),
            'email_enabled' => true,
            'in_app_enabled' => true,
            'min_alert_level' => $this->faker->randomElement(['info', 'warning', 'error', 'critical']),
        ];
    }
}
