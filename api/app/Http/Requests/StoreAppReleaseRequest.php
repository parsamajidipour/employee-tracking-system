<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAppReleaseRequest extends FormRequest
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
            'apk' => [
                'required',
                'file',
                'max:204800',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (strtolower($value->getClientOriginalExtension()) !== 'apk') {
                        $fail('The apk field must be an .apk file.');
                    }
                },
            ],
            'version_code' => ['required', 'integer', 'min:1', Rule::unique('app_releases', 'version_code')],
            'version_name' => ['required', 'string', 'max:32', 'regex:/^\d+\.\d+\.\d+$/'],
            'release_notes' => ['nullable', 'string', 'max:2000'],
            'is_mandatory' => ['nullable', 'boolean'],
        ];
    }
}
