<?php

namespace App\Http\Requests;

use App\Rules\EffectiveFromNotInPast;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:users,id'],
            'template_id' => ['required', 'exists:shift_templates,id'],
            'effective_from' => ['required', 'date', new EffectiveFromNotInPast()],
            'effective_to' => ['nullable', 'date', function (string $attribute, mixed $value, \Closure $fail) {
                if ($this->filled('effective_from') && CarbonImmutable::parse($value)->lessThanOrEqualTo(CarbonImmutable::parse($this->input('effective_from')))) {
                    $fail('The :attribute must be after effective_from.');
                }
            }],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
