<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class EmployeeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'username' => $this->username,
            'role' => $this->role,
            'is_active' => $this->is_active,
            'shifts' => $this->whenLoaded('employeeShifts', fn () => $this->employeeShifts->map(fn ($shift) => [
                'id' => $shift->template->id,
                'name' => $shift->template->name,
                'start_time' => $shift->template->start_time,
                'end_time' => $shift->template->end_time,
                'days_of_week' => $shift->template->days_of_week,
            ])->values()),
            'device' => $this->whenLoaded('activeDevice', fn () => $this->activeDevice === null ? null : [
                'device_identifier' => $this->activeDevice->device_identifier,
                'device_name' => $this->activeDevice->device_name,
                'last_seen_at' => $this->activeDevice->last_seen_at?->toISOString(),
            ], null),
        ];
    }
}
