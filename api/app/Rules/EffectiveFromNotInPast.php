<?php

namespace App\Rules;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * CLAUDE.md invariant 6: effective_from on a schedule change cannot be in
 * the past. Applied whenever a request sets employee_shifts.effective_from
 * — create or update — never applied retroactively to a value that isn't
 * actually being changed (see StoreEmployeeShiftRequest/
 * UpdateEmployeeShiftRequest for how callers avoid resubmitting an
 * unchanged historical value).
 */
class EffectiveFromNotInPast implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) && ! $value instanceof \DateTimeInterface) {
            return; // not this rule's job — a `date` rule elsewhere reports the type error
        }

        try {
            $instant = CarbonImmutable::parse($value)->utc();
        } catch (\Throwable) {
            return; // malformed — a `date` rule elsewhere reports this
        }

        if ($instant->lessThan(CarbonImmutable::now())) {
            $fail('The :attribute cannot be in the past.');
        }
    }
}
