<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTrackRequest extends FormRequest
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
            'points' => ['required', 'array', 'min:1'],
            'points.*.lat' => ['required', 'numeric', 'between:-90,90'],
            'points.*.lng' => ['required', 'numeric', 'between:-180,180'],
            'points.*.accuracy_m' => ['nullable', 'numeric', 'min:0'],
            'points.*.speed_mps' => ['nullable', 'numeric', 'min:0'],
            'points.*.heading_deg' => ['nullable', 'numeric', 'between:0,360'],
            'points.*.battery_pct' => ['nullable', 'integer', 'between:0,100'],
            'points.*.is_mocked' => ['required', 'boolean'],
            'points.*.recorded_at' => ['required', 'date'],
        ];
    }
}
