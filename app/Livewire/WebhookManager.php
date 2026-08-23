<?php

namespace App\Livewire;

use App\Jobs\DeliverWebhookJob;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Webhooks\WebhookEvent;
use App\Services\Webhooks\WebhookSignature;
use App\Services\Webhooks\WebhookUrlGuard;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Managing where events get pushed, without writing a line of code.
 *
 * The API can do all of this, but requiring an API token to set up the thing
 * that saves you from polling the API is a chicken-and-egg the person
 * configuring a pager does not need.
 */
class WebhookManager extends Component
{
    public string $url = '';

    public string $description = '';

    /** @var array<int, string> */
    public array $selectedEvents = [];

    public bool $subscribeToAll = true;

    public bool $showCreateModal = false;

    /**
     * Held in memory for exactly one render after creation. It is never read
     * back from the database because it cannot be -- this is the only moment
     * the plaintext secret exists outside the encrypted column.
     */
    public ?string $newSecret = null;

    public ?int $viewingDeliveriesFor = null;

    public function mount(): void
    {
        $this->authorize('viewAny', WebhookEndpoint::class);
    }

    /**
     * @return Collection<int, WebhookEndpoint>
     */
    public function getEndpointsProperty(): Collection
    {
        $query = WebhookEndpoint::query();
        $query->orderByDesc('id');

        return $query->get();
    }

    /**
     * @return Collection<int, WebhookDelivery>
     */
    public function getDeliveriesProperty(): Collection
    {
        if ($this->viewingDeliveriesFor === null) {
            return collect();
        }

        $endpoint = WebhookEndpoint::find($this->viewingDeliveriesFor);

        if ($endpoint === null) {
            return collect();
        }

        $query = $endpoint->deliveries()->getQuery();
        $query->orderByDesc('id');
        $query->limit(20);

        return $query->get();
    }

    /**
     * @return array<string, string>
     */
    public function getAvailableEventsProperty(): array
    {
        return WebhookEvent::CATALOGUE;
    }

    public function create(WebhookUrlGuard $guard): void
    {
        $this->authorize('create', WebhookEndpoint::class);

        $this->validate([
            'url' => 'required|url|max:2048',
            'description' => 'nullable|string|max:255',
        ]);

        $events = $this->subscribeToAll ? ['*'] : array_values($this->selectedEvents);

        if ($events === []) {
            $this->addError('selectedEvents', 'Choose at least one event, or subscribe to all of them.');

            return;
        }

        $rejection = $guard->rejectionReason($this->url);

        if ($rejection !== null) {
            $this->addError('url', $rejection);

            return;
        }

        $secret = WebhookSignature::newSecret();

        WebhookEndpoint::create([
            'team_id' => auth()->user()?->current_team_id,
            'created_by' => auth()->id(),
            'url' => $this->url,
            'description' => $this->description !== '' ? $this->description : null,
            'secret' => $secret,
            'events' => $events,
            'is_active' => true,
        ]);

        $this->newSecret = $secret;
        $this->showCreateModal = false;
        $this->reset(['url', 'description', 'selectedEvents']);
        $this->subscribeToAll = true;
    }

    public function toggleActive(int $endpointId): void
    {
        $endpoint = WebhookEndpoint::findOrFail($endpointId);
        $this->authorize('update', $endpoint);

        $endpoint->is_active = ! $endpoint->is_active;

        // Switching a fixed endpoint back on starts its failure count over,
        // so it is not one bad delivery away from being disabled again by
        // history it has nothing to do with.
        if ($endpoint->is_active) {
            $endpoint->consecutive_failures = 0;
            $endpoint->auto_disabled_at = null;
            $endpoint->last_failure_reason = null;
        }

        $endpoint->save();
    }

    public function sendTest(int $endpointId): void
    {
        $endpoint = WebhookEndpoint::findOrFail($endpointId);
        $this->authorize('update', $endpoint);

        $delivery = WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id,
            'event' => 'ping',
            'payload' => [
                'event' => 'ping',
                'occurred_at' => now()->toIso8601String(),
                'data' => ['message' => 'This is a test delivery from Mines.'],
            ],
            'status' => WebhookDelivery::STATUS_PENDING,
            'next_attempt_at' => now(),
        ]);

        DeliverWebhookJob::dispatch($delivery->id);

        $this->viewingDeliveriesFor = $endpoint->id;
        $this->dispatch('notify', message: 'Test queued. It is sent within about a minute.');
    }

    public function delete(int $endpointId): void
    {
        $endpoint = WebhookEndpoint::findOrFail($endpointId);
        $this->authorize('delete', $endpoint);

        $endpoint->delete();

        if ($this->viewingDeliveriesFor === $endpointId) {
            $this->viewingDeliveriesFor = null;
        }
    }

    public function showDeliveries(int $endpointId): void
    {
        $endpoint = WebhookEndpoint::findOrFail($endpointId);
        $this->authorize('view', $endpoint);

        $this->viewingDeliveriesFor = $this->viewingDeliveriesFor === $endpointId ? null : $endpointId;
    }

    public function dismissSecret(): void
    {
        $this->newSecret = null;
    }

    public function render(): View
    {
        return view('livewire.webhook-manager');
    }
}
