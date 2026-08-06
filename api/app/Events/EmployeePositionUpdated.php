<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The delta pushed to the `positions` private channel on each accepted
 * batch — see routes/channels.php for the channel's authorization (gated on
 * the view-locations capability) and App\Services\PositionPublisher, the
 * only place this is dispatched from. ShouldBroadcastNow, not ShouldBroadcast:
 * a live map has no use for a position update that sat in a queue.
 *
 * effectiveEnd travels with every delta (and every GET /api/v1/positions
 * snapshot row) so the panel can remove a marker the instant its window
 * ends without polling or guessing — it never has to compute this itself,
 * per CLAUDE.md invariant 8 (one resolver, no second implementation).
 */
final class EmployeePositionUpdated implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public readonly int $employeeId,
        public readonly string $name,
        public readonly ?string $teamName,
        public readonly float $lat,
        public readonly float $lng,
        public readonly ?float $accuracyM,
        public readonly ?int $batteryPct,
        public readonly string $recordedAt,
        public readonly string $effectiveEnd,
    ) {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('positions')];
    }

    public function broadcastAs(): string
    {
        return 'position.updated';
    }

    /**
     * @return array{employee_id: int, name: string, team_name: ?string, lat: float, lng: float, accuracy_m: ?float, battery_pct: ?int, recorded_at: string, effective_end: string}
     */
    public function broadcastWith(): array
    {
        return [
            'employee_id' => $this->employeeId,
            'name' => $this->name,
            'team_name' => $this->teamName,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'accuracy_m' => $this->accuracyM,
            'battery_pct' => $this->batteryPct,
            'recorded_at' => $this->recordedAt,
            'effective_end' => $this->effectiveEnd,
        ];
    }
}
