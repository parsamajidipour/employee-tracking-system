<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ShiftWindowResolver;
use App\ValueObjects\ShiftWindow;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Redis;

class PositionController extends Controller
{
    /**
     * Snapshot for the initial map load: last known position, per employee,
     * for every employee currently inside a window right now — re-checked
     * against ShiftWindowResolver at read time (CLAUDE.md invariant 5: no
     * endpoint returns location data recorded outside a shift window). This
     * is the actual guarantee; the last_known Redis key's own TTL (see
     * App\Services\PositionPublisher) is only a second line of defence.
     *
     * Each row's shape matches App\Events\EmployeePositionUpdated's delta
     * payload exactly, so the panel can treat a snapshot row and a delta as
     * the same thing wherever it merges them into its live state.
     */
    public function index(ShiftWindowResolver $resolver): JsonResponse
    {
        $now = CarbonImmutable::now();

        $positions = User::query()
            ->whereNotNull('team_id')
            ->get()
            ->map(fn (User $employee) => ['employee' => $employee, 'window' => $resolver->resolve($employee, $now)])
            ->filter(fn (array $pair) => $pair['window'] !== null)
            ->map(fn (array $pair) => $this->lastKnown($pair['employee'], $pair['window']))
            ->filter()
            ->values();

        return response()->json($positions);
    }

    /**
     * effective_end comes from $window — freshly resolved above at read
     * time, not from whatever was true when the cached point was written —
     * so a schedule change since the last point still reports the correct
     * removal time. Every other field is exactly what
     * App\Services\PositionPublisher cached, the same values its delta
     * broadcasts, so a snapshot row and a delta are shaped identically.
     *
     * @return array{employee_id: int, name: string, team_name: ?string, lat: float, lng: float, accuracy_m: ?float, battery_pct: ?int, recorded_at: string, effective_end: string}|null
     */
    private function lastKnown(User $employee, ShiftWindow $window): ?array
    {
        $raw = Redis::get("last_known:{$employee->id}");
        if ($raw === null) {
            return null;
        }

        $cached = json_decode($raw, true);

        return [
            'employee_id' => $employee->id,
            'name' => $cached['name'],
            'team_name' => $cached['team_name'],
            'lat' => $cached['lat'],
            'lng' => $cached['lng'],
            'accuracy_m' => $cached['accuracy_m'],
            'battery_pct' => $cached['battery_pct'],
            'recorded_at' => $cached['recorded_at'],
            'effective_end' => $window->effectiveEnd()->toISOString(),
        ];
    }
}
