<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShiftExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * employee_id is intentionally not editable here, same reasoning as
     * UpdateEmployeeShiftRequest.
     */
    public function rules(): array
    {
        return [
            'date' => ['sometimes', 'date_format:Y-m-d'],
            'type' => ['sometimes', Rule::in(['leave', 'holiday', 'overtime', 'early_end'])],
            'start_at' => ['nullable', 'date_format:H:i'],
            'end_at' => ['nullable', 'date_format:H:i'],
            'note' => ['nullable', 'string', 'max:1000'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
