<?php

namespace App\Http\Requests;

use App\Models\Report;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Report::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'type' => 'required|string|in:production,fuel,maintenance,haul,custom,compliance',
            'format' => 'required|string|in:pdf,csv,xlsx',
            'filters' => 'nullable|array',
            'filters.start_date' => 'nullable|date|before_or_equal:today',
            'filters.end_date' => 'nullable|date|after_or_equal:filters.start_date',
            'filters.machine_ids' => 'nullable|array',
            'filters.mine_area_id' => 'nullable|integer|exists:mine_areas,id',
        ];
    }
}
