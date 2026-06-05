<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMachineRequest extends FormRequest
{
    public function authorize(): bool
    {
        $machine = $this->route('machine');

        return $machine !== null && $this->user()->can('update', $machine);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'manufacturer' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'status' => 'sometimes|required|string|in:active,idle,maintenance,offline',
            'capacity' => 'nullable|numeric|min:0|max:9999',
            'fuel_capacity' => 'nullable|numeric|min:0|max:99999',
            'mine_area_id' => 'nullable|integer|exists:mine_areas,id',
            'notes' => 'nullable|string|max:2000',
        ];
    }
}
