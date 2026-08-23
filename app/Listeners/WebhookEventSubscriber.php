<?php

namespace App\Listeners;

use App\Services\Webhooks\WebhookDispatcher;
use App\Services\Webhooks\WebhookEvent;
use Illuminate\Events\Dispatcher;

/**
 * Turns the application's own domain events into outbound webhooks.
 *
 * Deliberately hung off the same events that already drive the live UI over
 * websockets, rather than a parallel set of webhook-only hooks. An integrator
 * sees exactly what an operator watching the screen sees, at the same moment,
 * and there is one place to add an event rather than two to keep in step.
 */
class WebhookEventSubscriber
{
    public function __construct(private readonly WebhookDispatcher $dispatcher) {}

    public function handle(object $event): void
    {
        $this->dispatcher->dispatch($event);
    }

    public function subscribe(Dispatcher $events): void
    {
        foreach (array_keys(WebhookEvent::SOURCES) as $source) {
            $events->listen($source, [self::class, 'handle']);
        }
    }
}
