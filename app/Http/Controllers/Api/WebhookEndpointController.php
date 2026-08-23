<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\WebhookDeliveryResource;
use App\Http\Resources\WebhookEndpointResource;
use App\Jobs\DeliverWebhookJob;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Webhooks\WebhookEvent;
use App\Services\Webhooks\WebhookSignature;
use App\Services\Webhooks\WebhookUrlGuard;
use App\Support\ApiPayload;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Webhook API Controller
 *
 * Manages where a team wants events pushed, so integrations stop polling.
 */
class WebhookEndpointController extends Controller
{
    public function __construct(private readonly WebhookUrlGuard $guard) {}

    /**
     * List webhook endpoints for current team
     *
     * GET /api/v1/webhooks
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', WebhookEndpoint::class);

        $validated = $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = WebhookEndpoint::query();
        $query->where('team_id', auth()->user()?->current_team_id);
        $query->orderByDesc('id');

        return ApiResponse::paginated(
            $query->paginate(ApiPayload::int($validated['per_page'] ?? null, 15)),
            WebhookEndpointResource::class,
            ['events_available' => WebhookEvent::CATALOGUE],
        );
    }

    /**
     * Create a webhook endpoint
     *
     * POST /api/v1/webhooks
     *
     * The signing secret is in this response and in no other. Store it.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', WebhookEndpoint::class);

        $validated = $request->validate([
            'url' => 'required|url|max:2048',
            'description' => 'nullable|string|max:255',
            'events' => 'required|array|min:1',
            'events.*' => ['string', Rule::in(array_merge(['*'], WebhookEvent::names()))],
        ]);

        $url = ApiPayload::str($validated['url']);
        $this->assertUrlIsSafe($url);

        $secret = WebhookSignature::newSecret();

        $endpoint = WebhookEndpoint::create([
            'team_id' => auth()->user()?->current_team_id,
            'created_by' => auth()->id(),
            'url' => $url,
            'description' => $validated['description'] ?? null,
            'secret' => $secret,
            'events' => $validated['events'],
            'is_active' => true,
        ]);

        return response()->json([
            'data' => WebhookEndpointResource::make($endpoint)->resolve(),
            'secret' => $secret,
            'message' => 'Store this secret now. It is not shown again.',
        ], 201);
    }

    /**
     * Get a single webhook endpoint
     *
     * GET /api/v1/webhooks/{webhook}
     */
    public function show(WebhookEndpoint $webhook): JsonResponse
    {
        $this->authorize('view', $webhook);

        return response()->json(['data' => WebhookEndpointResource::make($webhook)->resolve()]);
    }

    /**
     * Update a webhook endpoint
     *
     * PUT /api/v1/webhooks/{webhook}
     */
    public function update(Request $request, WebhookEndpoint $webhook): JsonResponse
    {
        $this->authorize('update', $webhook);

        $validated = $request->validate([
            'url' => 'sometimes|required|url|max:2048',
            'description' => 'nullable|string|max:255',
            'events' => 'sometimes|required|array|min:1',
            'events.*' => ['string', Rule::in(array_merge(['*'], WebhookEvent::names()))],
            'is_active' => 'sometimes|boolean',
        ]);

        if (isset($validated['url'])) {
            $this->assertUrlIsSafe(ApiPayload::str($validated['url']));
        }

        $webhook->fill($validated);

        // Turning an endpoint back on starts its failure count over, so a
        // fixed receiver is not one bad delivery from being switched off
        // again by history it has nothing to do with.
        if ($webhook->isDirty('is_active') && $webhook->is_active) {
            $webhook->consecutive_failures = 0;
            $webhook->auto_disabled_at = null;
            $webhook->last_failure_reason = null;
        }

        $webhook->save();

        return response()->json(['data' => WebhookEndpointResource::make($webhook)->resolve()]);
    }

    /**
     * Delete a webhook endpoint
     *
     * DELETE /api/v1/webhooks/{webhook}
     */
    public function destroy(WebhookEndpoint $webhook): JsonResponse
    {
        $this->authorize('delete', $webhook);

        $webhook->delete();

        return response()->json(['message' => 'Webhook endpoint deleted.']);
    }

    /**
     * List recent deliveries for a webhook endpoint
     *
     * GET /api/v1/webhooks/{webhook}/deliveries
     */
    public function deliveries(Request $request, WebhookEndpoint $webhook): JsonResponse
    {
        $this->authorize('view', $webhook);

        $validated = $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'status' => 'nullable|string|in:pending,delivered,failed',
        ]);

        $query = $webhook->deliveries()->getQuery();
        $query->orderByDesc('id');

        if (isset($validated['status'])) {
            $query->where('status', ApiPayload::str($validated['status']));
        }

        return ApiResponse::paginated(
            $query->paginate(ApiPayload::int($validated['per_page'] ?? null, 15)),
            WebhookDeliveryResource::class,
        );
    }

    /**
     * Send a test event to a webhook endpoint
     *
     * POST /api/v1/webhooks/{webhook}/test
     *
     * Delivers a `ping` immediately so a receiver can be verified before real
     * events depend on it. `ping` is not subscribable -- it is only ever sent
     * from here.
     */
    public function test(WebhookEndpoint $webhook): JsonResponse
    {
        $this->authorize('update', $webhook);

        $delivery = WebhookDelivery::create([
            'webhook_endpoint_id' => $webhook->id,
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

        return response()->json([
            'data' => WebhookDeliveryResource::make($delivery)->resolve(),
            'message' => 'Test delivery queued.',
        ], 202);
    }

    /**
     * A rejected URL is a validation failure, not a 500: the caller gave us
     * an address we will not fetch, and the reason is the useful part.
     */
    private function assertUrlIsSafe(string $url): void
    {
        $reason = $this->guard->rejectionReason($url);

        if ($reason !== null) {
            throw ValidationException::withMessages(['url' => $reason]);
        }
    }
}
