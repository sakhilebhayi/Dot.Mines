<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreFeedPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => 'required|string|max:10000',
            'category' => 'required|string|in:general,safety,maintenance,production,incident',
            'priority' => 'required|string|in:normal,high,critical',
            'shift' => 'nullable|string|in:day,night,afternoon',
            'mine_area_id' => 'nullable|integer|exists:mine_areas,id',
        ];
    }
}
