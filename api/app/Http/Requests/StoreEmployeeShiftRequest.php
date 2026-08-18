<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeShiftRequest extends FormRequest
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
            'employee_id' => ['required', 'exists:users,id'],
            'template_id' => ['required', 'exists:shift_templates,id'],
            'effective_from' => ['required', 'date', function ($attribute, $value, $fail) {
                if (CarbonImmutable::parse($value)->lessThan(CarbonImmutable::now())) {
                    $fail('The :attribute cannot be in the past.');
                }
            }],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
