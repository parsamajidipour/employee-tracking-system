<?php

namespace App\Http\Requests;

use App\Models\EmployeeLeave;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeLeaveRequest extends FormRequest
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
            'starts_at' => ['required', 'date', 'after_or_equal:now'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'note' => ['nullable', 'string', 'max:1000'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'starts_at.after_or_equal' => 'A leave cannot start in the past — it can only change what is tracked from now on.',
            'ends_at.after' => 'The leave must end after it starts.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $overlaps = EmployeeLeave::where('employee_id', $this->route('employee')->id)
                ->overlapping($this->startsAt(), $this->endsAt())
                ->exists();

            if ($overlaps) {
                $validator->errors()->add('starts_at', 'This employee already has a leave covering part of that range.');
            }
        });
    }

    public function startsAt(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->validated('starts_at'))->utc();
    }

    public function endsAt(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->validated('ends_at'))->utc();
    }
}
