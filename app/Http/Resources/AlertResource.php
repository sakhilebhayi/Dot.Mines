<?php

namespace App\Http\Resources;

use App\Models\Alert;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An operational alert raised against a machine or mine area.
 *
 * The acknowledged/resolved actors are exposed as summaries -- the raw
 * relations previously serialized whole User models (email, 2FA state,
 * notification preferences) into every alert payload.
 *
 * @mixin Alert
 */
class AlertResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority,
            'status' => $this->status,

            'machine_id' => $this->machine_id,
            'mine_area_id' => $this->mine_area_id,

            'triggered_at' => $this->triggered_at->toIso8601String(),
            'acknowledged_at' => $this->acknowledged_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'acknowledged_by' => $this->acknowledged_by,
            'resolved_by' => $this->resolved_by,

            'metadata' => $this->metadata,

            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),

            'machine' => MachineResource::make($this->whenLoaded('machine')),
            'acknowledged_by_user' => UserSummaryResource::make($this->whenLoaded('acknowledgedBy')),
            'resolved_by_user' => UserSummaryResource::make($this->whenLoaded('resolvedBy')),
        ];
    }
}
