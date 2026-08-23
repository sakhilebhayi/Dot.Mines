<?php

namespace App\Services\Webhooks;

use App\Jobs\DeliverWebhookJob;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Fans one domain event out to the endpoints that asked for it.
 *
 * Everything here runs on the dispatching request or job, so it does exactly
 * two things -- pick the endpoints, write a delivery row -- and hands the
 * network call to the queue. A slow or dead receiver must never be able to
 * slow down the thing that raised the event.
 */
class WebhookDispatcher
{
    /**
     * @return int how many deliveries were queued
     */
    public function dispatch(object $event): int
    {
        $name = WebhookEvent::SOURCES[$event::class] ?? null;

        if ($name === null) {
            return 0;
        }

        $teamId = WebhookEvent::teamIdFor($event);

        if ($teamId === null) {
            // A parent record vanished between dispatch and handling. Dropping
            // the event is the only safe option: with no team, there is no
            // way to know whose endpoints should see it.
            Log::warning('Webhook event dropped: no team could be resolved.', ['event' => $name]);

            return 0;
        }

        $data = WebhookEvent::dataFor($event);

        if ($data === null) {
            return 0;
        }

        $queued = 0;

        foreach ($this->endpointsFor($teamId, $name) as $endpoint) {
            $delivery = WebhookDelivery::create([
                'webhook_endpoint_id' => $endpoint->id,
                'event' => $name,
                'payload' => [
                    'event' => $name,
                    'occurred_at' => now()->toIso8601String(),
                    'data' => $data,
                ],
                'status' => WebhookDelivery::STATUS_PENDING,
                'next_attempt_at' => now(),
            ]);

            DeliverWebhookJob::dispatch($delivery->id);
            $queued++;
        }

        return $queued;
    }

    /**
     * The team's active endpoints that subscribe to this event.
     *
     * Read without the team scope on purpose: this runs from queued jobs and
     * scheduled commands where there is no authenticated user, so the global
     * scope would filter on nothing. The team is filtered explicitly instead,
     * which is also the line that keeps one team's events out of another
     * team's endpoint.
     *
     * @return Collection<int, WebhookEndpoint>
     */
    private function endpointsFor(int $teamId, string $event): Collection
    {
        $query = WebhookEndpoint::withoutTeamFilter();
        $query->where('team_id', $teamId);
        $query->where('is_active', true);

        return $query->get()->filter(
            static fn (WebhookEndpoint $endpoint): bool => $endpoint->wantsEvent($event)
        )->values();
    }
}
