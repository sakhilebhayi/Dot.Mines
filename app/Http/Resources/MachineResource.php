<?php

namespace App\Http\Resources;

use App\Models\Machine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Machine
 */
class MachineResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'machine_type' => $this->machine_type,
            'manufacturer' => $this->manufacturer,
            'model' => $this->model,
            'year_of_manufacture' => $this->year_of_manufacture,
            'registration_number' => $this->registration_number,
            'serial_number' => $this->serial_number,
            'capacity' => $this->capacity,
            'fuel_capacity' => $this->fuel_capacity,
            'hours_meter' => $this->hours_meter,
            'status' => $this->status,
            'location' => [
                'latitude' => $this->last_location_latitude,
                'longitude' => $this->last_location_longitude,
                'updated_at' => $this->last_location_update,
            ],
            'mine_area_id' => $this->mine_area_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
