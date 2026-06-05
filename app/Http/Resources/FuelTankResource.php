<?php

namespace App\Http\Resources;

use App\Models\FuelTank;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FuelTank
 */
class FuelTankResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $capacity = (float) $this->capacity_liters;
        $currentLevel = (float) $this->current_level_liters;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'location' => $this->location_description,
            'fuel_type' => $this->fuel_type,
            'capacity_liters' => $capacity,
            'current_level_liters' => $currentLevel,
            'minimum_level_liters' => (float) $this->minimum_level_liters,
            'fill_percentage' => $capacity > 0
                ? round(($currentLevel / $capacity) * 100, 1)
                : 0,
            'status' => $this->status,
            'last_refueled_at' => $this->last_inspection_date,
            'created_at' => $this->created_at,
        ];
    }
}
