<?php

namespace App\Http\Requests;

use App\Models\Alert;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Alert::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'machine_id' => 'required|integer|exists:machines,id',
            'mine_area_id' => 'nullable|integer|exists:mine_areas,id',
            'type' => 'required|string|max:50',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'priority' => 'required|string|in:critical,high,medium,low',
            'metadata' => 'nullable|array',
        ];
    }
}
