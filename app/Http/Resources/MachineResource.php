<?php

namespace App\Http\Resources;

use App\Models\Machine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A machine in the fleet.
 *
 * Hidden on purpose (internal plumbing, not fleet data): team_id (every
 * request is already tenant-scoped), sync_version (the local-cache
 * replication counter), allocation_state (the billing entitlement state
 * machine), integration_id / manufacturer_id (provider wiring),
 * excavator_id / assigned_to_excavator_at (internal ADT pairing).
 *
 * @mixin Machine
 */
class MachineResource extends JsonResource
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
            'machine_type' => $this->machine_type,
            'model' => $this->model,
            'manufacturer' => $this->manufacturer,
            'registration_number' => $this->registration_number,
            'serial_number' => $this->serial_number,
            'year_of_manufacture' => $this->year_of_manufacture,
            'status' => $this->status,

            'capacity' => $this->capacity,
            'fuel_capacity' => $this->fuel_capacity,
            'hours_meter' => $this->hours_meter,
            'operating_hours' => $this->operating_hours,
            'odometer' => $this->odometer,
            'total_distance_km' => $this->total_distance_km,

            'mine_area_id' => $this->mine_area_id,
            'notes' => $this->notes,

            // Last known position from telemetry. `updated_at` here is the
            // reading's own timestamp, not when the row was written.
            'location' => [
                'latitude' => $this->last_location_latitude,
                'longitude' => $this->last_location_longitude,
                'updated_at' => $this->last_location_update?->toIso8601String(),
            ],

            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),

            'mine_area' => MineAreaSummaryResource::make($this->whenLoaded('mineArea')),
            'alerts' => AlertResource::collection($this->whenLoaded('alerts')),
        ];
    }
}
