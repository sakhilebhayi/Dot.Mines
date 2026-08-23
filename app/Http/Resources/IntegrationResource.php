<?php

namespace App\Http\Resources;

use App\Models\Integration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A manufacturer/telematics integration.
 *
 * `credentials` is never exposed. It is an encrypted:json column holding
 * real third-party secrets (OAuth client secrets, API passwords) that
 * decrypt transparently on read -- so any endpoint returning this model raw
 * would serialize them in clear text. The controllers happened to hand-pick
 * columns; this resource makes that structural rather than remembered.
 *
 * @mixin Integration
 */
class IntegrationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'name' => $this->name,
            'status' => $this->status,

            'machines_count' => $this->machines_count,
            'capabilities' => $this->capabilities,
            'sync_streams' => $this->sync_streams,

            'last_sync_at' => $this->last_sync_at?->toIso8601String(),
            'last_sync_status' => $this->last_sync_status,
            'last_error' => $this->last_error,

            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
