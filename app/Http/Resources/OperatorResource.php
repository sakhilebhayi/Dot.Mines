<?php

namespace App\Http\Resources;

use App\Models\Operator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A machine operator as the API shows them: identity and employment, no
 * contact details, no emergency contacts, no medical data, no photo path.
 * Those stay behind their own permissions in the application.
 *
 * @mixin Operator
 */
class OperatorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_number' => $this->employee_number,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'preferred_name' => $this->preferred_name,
            'name' => $this->name,
            'department' => $this->department,
            'job_title' => $this->job_title,
            'employment_type' => $this->employment_type,
            'employment_status' => $this->employment_status,
            'employed_from' => $this->employed_from?->toDateString(),
            'employed_until' => $this->employed_until?->toDateString(),
            'default_shift' => $this->default_shift,
            'mine_area_id' => $this->mine_area_id,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
