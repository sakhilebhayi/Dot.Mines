<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsTimestamps;
use App\Models\MachineHealthStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A machine's current health assessment.
 *
 * @mixin MachineHealthStatus
 */
class MachineHealthResource extends JsonResource
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
            'machine_id' => $this->machine_id,

            'overall_health_score' => $this->overall_health_score,
            'health_status' => $this->health_status,
            'component_scores' => $this->component_scores,

            'components' => [
                'engine' => $this->engine_health,
                'transmission' => $this->transmission_health,
                'hydraulics' => $this->hydraulics_health,
                'electrical' => $this->electrical_health,
                'brakes' => $this->brakes_health,
                'cooling_system' => $this->cooling_system_health,
            ],

            'active_fault_codes' => $this->active_fault_codes,
            'fault_code_count' => $this->fault_code_count,
            'recommendations' => $this->recommendations,
            'last_diagnostic_scan' => $this->iso($this->last_diagnostic_scan),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            'machine' => MachineResource::make($this->whenLoaded('machine')),
        ];
    }
}
