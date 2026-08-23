<?php

namespace App\Http\Resources;

use App\Models\Geofence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A geofenced zone.
 *
 * @mixin Geofence
 */
class GeofenceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'status' => $this->status,

            'mine_area_id' => $this->mine_area_id,

            'coordinates' => $this->coordinates,
            'center' => [
                'latitude' => $this->center_latitude,
                'longitude' => $this->center_longitude,
            ],
            'area_sqm' => $this->area_sqm,
            'perimeter_m' => $this->perimeter_m,

            'notes' => $this->notes,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),

            'mine_area' => MineAreaSummaryResource::make($this->whenLoaded('mineArea')),
        ];
    }
}
