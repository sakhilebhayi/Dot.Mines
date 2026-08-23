<?php

namespace App\Http\Resources;

use App\Models\MaintenanceRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A maintenance work order.
 *
 * The assigned/completing technicians are summaries; the raw relations
 * previously serialized whole User models, and the record listing also
 * eager-loaded and serialized the entire Team.
 *
 * @mixin MaintenanceRecord
 */
class MaintenanceRecordResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'work_order_number' => $this->work_order_number,
            'maintenance_type' => $this->maintenance_type,
            'title' => $this->title,
            'description' => $this->description,
            'work_performed' => $this->work_performed,
            'status' => $this->status,
            'priority' => $this->priority,

            'machine_id' => $this->machine_id,
            'maintenance_schedule_id' => $this->maintenance_schedule_id,
            'assigned_to' => $this->assigned_to,
            'completed_by' => $this->completed_by,

            'scheduled_date' => $this->scheduled_date->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),

            'labor_hours' => $this->labor_hours,
            'labor_cost' => $this->labor_cost,
            'parts_cost' => $this->parts_cost,
            'total_cost' => $this->total_cost,
            'parts_used' => $this->parts_used,

            'fault_codes_cleared' => $this->fault_codes_cleared,
            'odometer_reading' => $this->odometer_reading,
            'hour_meter_reading' => $this->hour_meter_reading,
            'technician_notes' => $this->technician_notes,
            'machine_operational' => $this->machine_operational,

            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),

            'machine' => MachineResource::make($this->whenLoaded('machine')),
            'assigned_to_user' => UserSummaryResource::make($this->whenLoaded('assignedTo')),
            'completed_by_user' => UserSummaryResource::make($this->whenLoaded('completedBy')),
        ];
    }
}
