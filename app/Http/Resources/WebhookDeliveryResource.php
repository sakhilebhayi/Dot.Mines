<?php

namespace App\Http\Resources;

use App\Models\WebhookDelivery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One delivery attempt sequence, so a failure can be diagnosed without
 * asking us what we sent.
 *
 * @mixin WebhookDelivery
 */
class WebhookDeliveryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event,
            'status' => $this->status,
            'attempts' => $this->attempts,
            'response_status' => $this->response_status,
            'error' => $this->error,
            'duration_ms' => $this->duration_ms,
            'payload' => $this->payload,
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'next_attempt_at' => $this->next_attempt_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
