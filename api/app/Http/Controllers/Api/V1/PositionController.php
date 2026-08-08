<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ShiftWindowResolver;
use App\ValueObjects\ShiftWindow;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;

class PositionController extends Controller
{
    public function index(ShiftWindowResolver $resolver): JsonResponse
    {
        $now = CarbonImmutable::now();

        $positions = User::query()
            ->employees()
            ->active()
            ->get()
            ->map(fn (User $employee) => ['employee' => $employee, 'window' => $resolver->resolve($employee, $now)])
            ->filter(fn (array $pair) => $pair['window'] !== null)
            ->map(fn (array $pair) => $this->lastKnown($pair['employee'], $pair['window']))
            ->filter()
            ->values();

        return response()->json($positions);
    }

    public function distance(User $employee, Request $request): JsonResponse
    {
        $days = min(max($request->integer('days', 1), 1), config('tracking.retention_days'));
        $from = CarbonImmutable::now()->utc()->subDays($days - 1)->startOfDay();

        return response()->json([
            'employee_id' => $employee->id,
            'distance_m' => (float) DB::table('location_points')
                ->where('employee_id', $employee->id)
                ->where('recorded_at', '>=', $from)
                ->sum('distance_m'),
            'from' => $from->toISOString(),
            'to' => CarbonImmutable::now()->utc()->toISOString(),
        ]);
    }

    /**
     * @return array{employee_id: int, name: string, lat: float, lng: float, accuracy_m: ?float, battery_pct: ?int, recorded_at: string, effective_end: string}|null
     */
    private function lastKnown(User $employee, ShiftWindow $window): ?array
    {
        $raw = Redis::get("last_known:{$employee->id}");
        if ($raw === null) {
            return null;
        }

        $cached = json_decode($raw, true);
        $online = CarbonImmutable::parse($cached['recorded_at'])->greaterThanOrEqualTo(
            CarbonImmutable::now()->subSeconds(config('tracking.online_threshold_seconds')),
        );

        return [
            'employee_id' => $employee->id,
            'name' => $cached['name'],
            'lat' => $cached['lat'],
            'lng' => $cached['lng'],
            'accuracy_m' => $cached['accuracy_m'],
            'battery_pct' => $cached['battery_pct'],
            'recorded_at' => $cached['recorded_at'],
            'effective_end' => $window->effectiveEnd()->toISOString(),
            'distance_today_m' => $cached['distance_today_m'] ?? 0,
            'connection_status' => $online ? 'online' : 'offline',
        ];
    }
}
