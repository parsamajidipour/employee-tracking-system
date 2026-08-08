<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ShiftTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'days_of_week' => ['required', 'array', 'min:1', 'max:7'],
            'days_of_week.*' => ['integer', 'between:0,6', 'distinct'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'grace_before_min' => ['nullable', 'integer', 'min:0', 'max:720'],
            'grace_after_min' => ['nullable', 'integer', 'min:0', 'max:720'],
            'max_daily_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
        ];
    }
}
