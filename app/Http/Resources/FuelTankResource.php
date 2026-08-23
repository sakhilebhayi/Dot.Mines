<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsTimestamps;
use App\Models\FuelTank;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A bulk fuel tank.
 *
 * The derived readings (fill percentage, available capacity, and the
 * critical/below-minimum flags) are computed by the model and included here
 * so consumers do not re-implement the thresholds.
 *
 * @mixin FuelTank
 */
class FuelTankResource extends JsonResource
{
    use FormatsTimestamps;

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'tank_number' => $this->tank_number,
            'fuel_type' => $this->fuel_type,
            'status' => $this->status,

            'mine_area_id' => $this->mine_area_id,
            'location_description' => $this->location_description,
            'location' => [
                'latitude' => $this->location_latitude,
                'longitude' => $this->location_longitude,
            ],

            'capacity_liters' => $this->capacity_liters,
            'current_level_liters' => $this->current_level_liters,
            'minimum_level_liters' => $this->minimum_level_liters,
            'fill_percentage' => $this->fill_percentage,
            'available_capacity' => $this->available_capacity,
            'is_critical' => $this->isCritical(),
            'is_below_minimum' => $this->isBelowMinimum(),

            'current_price_per_liter' => $this->current_price_per_liter,
            'currency' => $this->currency,

            'last_inspection_date' => $this->iso($this->last_inspection_date),
            'next_inspection_date' => $this->iso($this->next_inspection_date),
            'notes' => $this->notes,

            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),

            'mine_area' => MineAreaSummaryResource::make($this->whenLoaded('mineArea')),
        ];
    }
}
