<?php

namespace Database\Factories;

use App\Models\Integration;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Integration>
 */
class IntegrationFactory extends Factory
{
    protected $model = Integration::class;

    public function definition(): array
    {
        $provider = $this->faker->randomElement(['volvo', 'cat', 'komatsu', 'bell', 'c_track', 'roundebult', 'kawasaki']);

        return [
            'team_id' => Team::factory(),
            'provider' => $provider,
            'name' => ucfirst($provider).' Integration',
            'api_key' => $this->faker->uuid(),
            'api_secret' => $this->faker->sha256(),
            'credentials' => [
                'client_id' => $this->faker->uuid(),
                'client_secret' => $this->faker->sha256(),
            ],
            'webhook_url' => $this->faker->url(),
            'webhook_secret' => $this->faker->sha256(),
            'status' => $this->faker->randomElement(['connected', 'disconnected', 'error']),
            'last_sync_at' => $this->faker->optional()->dateTimeBetween('-1 week', 'now'),
            'last_sync_status' => $this->faker->randomElement(['success', 'failed']),
            'last_error' => $this->faker->optional()->sentence(),
            'machines_count' => $this->faker->numberBetween(0, 50),
            'config' => [
                'sync_interval' => $this->faker->randomElement([5, 15, 30, 60]),
                'enabled_features' => $this->faker->randomElements(['location', 'metrics', 'alerts'], $this->faker->numberBetween(1, 3)),
            ],
        ];
    }

    /**
     * Indicate that the integration is connected.
     */
    public function connected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'connected',
            'last_sync_status' => 'success',
            'last_sync_at' => now(),
            'last_error' => null,
        ]);
    }

    /**
     * Indicate that the integration is disconnected.
     */
    public function disconnected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'disconnected',
            'last_sync_at' => null,
            'last_sync_status' => null,
        ]);
    }

    /**
     * Indicate that the integration has an error.
     */
    public function errored(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'error',
            'last_sync_status' => 'failed',
            'last_error' => $this->faker->sentence(),
        ]);
    }

    /**
     * Set a specific provider.
     */
    public function forProvider(string $provider): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => $provider,
            'name' => ucfirst($provider).' Integration',
        ]);
    }
}
