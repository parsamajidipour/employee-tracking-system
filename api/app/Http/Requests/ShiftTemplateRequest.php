<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ShiftTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'team_id' => ['required', 'exists:teams,id'],
            'name' => ['required', 'string', 'max:255'],
            // Informational only, per the migration comment — the resolver
            // never reads this column. Still required so the row can't be
            // created without stating one.
            'timezone' => ['required', 'string', 'timezone'],
            'days_of_week' => ['required', 'array', 'min:1'],
            'days_of_week.*' => ['integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'grace_before_min' => ['nullable', 'integer', 'min:0'],
            'grace_after_min' => ['nullable', 'integer', 'min:0'],
            'max_daily_minutes' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
