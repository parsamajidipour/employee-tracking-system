<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTrackingInterruptionRequest extends FormRequest
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
            'reason' => ['required', 'string', 'in:gps_disabled,network_disabled,flight_mode,permission_revoked,service_interrupted'],
            'at' => ['required', 'date'],
        ];
    }
}
