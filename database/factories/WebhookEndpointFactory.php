<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\WebhookEndpoint;
use App\Services\Webhooks\WebhookEvent;
use App\Services\Webhooks\WebhookSignature;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookEndpoint>
 */
class WebhookEndpointFactory extends Factory
{
    protected $model = WebhookEndpoint::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'created_by' => null,
            'url' => 'https://hooks.example.com/'.fake()->uuid(),
            'description' => fake()->sentence(3),
            'secret' => WebhookSignature::newSecret(),
            'events' => ['*'],
            'is_active' => true,
            'consecutive_failures' => 0,
        ];
    }

    /**
     * Subscribed to specific events rather than everything.
     *
     * @param  list<string>  $events
     */
    public function subscribedTo(array $events): static
    {
        return $this->state(fn (): array => ['events' => $events]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    /**
     * One failed delivery away from switching itself off.
     */
    public function onLastLegs(): static
    {
        return $this->state(fn (): array => [
            'consecutive_failures' => WebhookEndpoint::FAILURES_BEFORE_AUTO_DISABLE - 1,
        ]);
    }

    public function subscribedToAlerts(): static
    {
        return $this->subscribedTo([WebhookEvent::ALERT_TRIGGERED]);
    }
}
