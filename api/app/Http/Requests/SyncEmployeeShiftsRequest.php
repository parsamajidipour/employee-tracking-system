<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncEmployeeShiftsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shift_template_ids' => ['present', 'array'],
            'shift_template_ids.*' => ['integer', 'distinct', 'exists:shift_templates,id'],
        ];
    }
}
