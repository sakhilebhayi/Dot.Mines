<?php

namespace App\Http\Resources;

use App\Models\WebhookEndpoint;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A webhook endpoint, without its secret.
 *
 * The secret is returned exactly once, by the create response, and never
 * appears here -- a field that is sometimes present and sometimes redacted is
 * how secrets end up in logs and support tickets.
 *
 * @mixin WebhookEndpoint
 */
class WebhookEndpointResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'description' => $this->description,
            'events' => $this->events,
            'is_active' => $this->is_active,

            'health' => [
                'consecutive_failures' => $this->consecutive_failures,
                'last_success_at' => $this->last_success_at?->toIso8601String(),
                'last_failure_at' => $this->last_failure_at?->toIso8601String(),
                'last_failure_reason' => $this->last_failure_reason,
                'auto_disabled_at' => $this->auto_disabled_at?->toIso8601String(),
            ],

            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
