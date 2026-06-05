<?php

namespace App\Http\Requests;

use App\Models\Machine;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMachineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Machine::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'machine_type' => 'required|string|in:volvo,cat,komatsu,bell,ldv',
            'manufacturer' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'registration_number' => 'required|string|max:50|unique:machines,registration_number',
            'serial_number' => 'required|string|max:100|unique:machines,serial_number',
            'manufacturer_id' => 'nullable|string|max:255',
            'capacity' => 'nullable|numeric|min:0|max:9999',
            'fuel_capacity' => 'nullable|numeric|min:0|max:99999',
            'mine_area_id' => 'nullable|integer|exists:mine_areas,id',
            'notes' => 'nullable|string|max:2000',
        ];
    }
}
