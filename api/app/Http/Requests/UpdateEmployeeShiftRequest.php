<?php

namespace App\Http\Requests;

use App\Rules\EffectiveFromNotInPast;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * employee_id is intentionally not editable here — reassigning a
     * historical override row to a different employee isn't a "schedule
     * change," it's a data-integrity footgun. Create a new row instead.
     */
    public function rules(): array
    {
        return [
            'template_id' => ['sometimes', 'exists:shift_templates,id'],
            // The invariant-6 check only fires when effective_from is
            // present in the payload at all. The panel only sends it when
            // the admin is actually changing it, so this never blocks
            // resubmitting an already-past, unchanged value (which would
            // otherwise be impossible to edit at all once time moves on).
            'effective_from' => ['sometimes', 'date', new EffectiveFromNotInPast()],
            'effective_to' => ['nullable', 'date', function (string $attribute, mixed $value, \Closure $fail) {
                $effectiveFrom = $this->input('effective_from') ?? $this->route('employee_shift')?->effective_from;
                if ($effectiveFrom !== null && CarbonImmutable::parse($value)->lessThanOrEqualTo(CarbonImmutable::parse($effectiveFrom))) {
                    $fail('The :attribute must be after effective_from.');
                }
            }],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
