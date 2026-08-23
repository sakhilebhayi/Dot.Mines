<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsTimestamps;
use App\Models\MaintenanceSchedule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A recurring maintenance schedule (by hours, distance, or calendar).
 *
 * @mixin MaintenanceSchedule
 */
class MaintenanceScheduleResource extends JsonResource
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
            'title' => $this->title,
            'description' => $this->description,
            'maintenance_type' => $this->maintenance_type,
            'schedule_type' => $this->schedule_type,
            'priority' => $this->priority,
            'status' => $this->status,

            'machine_id' => $this->machine_id,

            'interval_hours' => $this->interval_hours,
            'interval_km' => $this->interval_km,
            'interval_days' => $this->interval_days,

            'last_service_hours' => $this->last_service_hours,
            'last_service_km' => $this->last_service_km,
            'last_service_date' => $this->iso($this->last_service_date),

            'next_service_hours' => $this->next_service_hours,
            'next_service_km' => $this->next_service_km,
            'next_service_date' => $this->iso($this->next_service_date),

            'estimated_cost' => $this->estimated_cost,
            'estimated_duration_hours' => $this->estimated_duration_hours,
            'required_parts' => $this->required_parts,
            'required_tools' => $this->required_tools,
            'auto_generate_work_order' => $this->auto_generate_work_order,

            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),

            'machine' => MachineResource::make($this->whenLoaded('machine')),
        ];
    }
}
