<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShiftExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:users,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'type' => ['required', Rule::in(['leave', 'holiday', 'overtime', 'early_end'])],
            // start_at/end_at only mean something for overtime/early_end —
            // the controller forces them to null for leave/holiday
            // regardless of what's submitted here.
            'start_at' => ['required_if:type,overtime,early_end', 'nullable', 'date_format:H:i'],
            'end_at' => ['required_if:type,overtime,early_end', 'nullable', 'date_format:H:i'],
            'note' => ['nullable', 'string', 'max:1000'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
