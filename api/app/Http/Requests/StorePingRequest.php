<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePingRequest extends FormRequest
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
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_m' => ['nullable', 'numeric', 'min:0'],
            'battery_pct' => ['nullable', 'integer', 'between:0,100'],
            'recorded_at' => ['required', 'date'],
        ];
    }
}
