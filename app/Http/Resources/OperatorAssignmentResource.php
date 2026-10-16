<?php

namespace App\Http\Resources;

use App\Models\OperatorMachineAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One stretch of one operator on one machine. `unassigned_at` null means
 * the operator is on the machine now.
 *
 * @mixin OperatorMachineAssignment
 */
class OperatorAssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'operator_id' => $this->operator_id,
            'machine_id' => $this->machine_id,
            'shift' => $this->shift,
            'assigned_at' => $this->assigned_at->toIso8601String(),
            'unassigned_at' => $this->unassigned_at?->toIso8601String(),
            'reason' => $this->reason,
            'was_override' => $this->was_override,
            'override_reason' => $this->override_reason,
            'operator' => OperatorResource::make($this->whenLoaded('operator')),
            'machine' => $this->whenLoaded('machine', fn (): array => ['id' => $this->machine?->id, 'name' => $this->machine?->name]),
        ];
    }
}
