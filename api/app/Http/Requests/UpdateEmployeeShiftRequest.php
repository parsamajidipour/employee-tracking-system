<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeShiftRequest extends FormRequest
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
            'template_id' => ['sometimes', 'exists:shift_templates,id'],
            'effective_from' => ['sometimes', 'date', function ($attribute, $value, $fail) {
                if (CarbonImmutable::parse($value)->lessThan(CarbonImmutable::now())) {
                    $fail(__('messages.date_in_past', ['attribute' => __('validation.attributes.'.$attribute)]));
                }
            }],
            'effective_to' => ['sometimes', 'nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
