<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

final class EmployeePositionUpdated implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public readonly int $employeeId,
        public readonly string $name,
        public readonly float $lat,
        public readonly float $lng,
        public readonly ?float $accuracyM,
        public readonly ?int $batteryPct,
        public readonly string $recordedAt,
        public readonly string $effectiveEnd,
        public readonly float $distanceTodayM,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('positions')];
    }

    public function broadcastAs(): string
    {
        return 'position.updated';
    }

    /**
     * @return array{employee_id: int, name: string, lat: float, lng: float, accuracy_m: ?float, battery_pct: ?int, recorded_at: string, effective_end: string}
     */
    public function broadcastWith(): array
    {
        return [
            'employee_id' => $this->employeeId,
            'name' => $this->name,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'accuracy_m' => $this->accuracyM,
            'battery_pct' => $this->batteryPct,
            'recorded_at' => $this->recordedAt,
            'effective_end' => $this->effectiveEnd,
            'distance_today_m' => $this->distanceTodayM,
            'connection_status' => 'online',
        ];
    }
}
