<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AccessAuditAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccessAuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeHistoryController extends Controller
{
    public function __construct(private readonly AccessAuditLogger $audit) {}

    /**
     * Reads whatever was actually recorded for that calendar day, grouped by
     * the tracking_sessions boundaries set at ingest time. Deliberately does
     * not re-resolve the employee's current shift schedule: a later shift
     * change or deletion must never make an already-recorded day unreadable.
     */
    public function trail(Request $request, User $employee): JsonResponse
    {
        $validated = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $timezone = config('tracking.timezone');
        $date = $validated['date'] ?? CarbonImmutable::now($timezone)->toDateString();
        $startAt = CarbonImmutable::parse($date, $timezone)->startOfDay()->utc();
        $endAt = $startAt->addDay();

        $this->audit->record($request->user(), $employee->id, AccessAuditAction::ViewTrail, $request->ip());

        $rows = DB::table('location_points')
            ->where('employee_id', $employee->id)
            ->whereBetween('recorded_at', [$startAt, $endAt])
            ->orderBy('recorded_at')
            ->selectRaw('ST_X(location::geometry) AS lng, ST_Y(location::geometry) AS lat, distance_m, accuracy_m, speed_mps, heading_deg, battery_pct, recorded_at, session_id')
            ->get();

        if ($rows->isEmpty()) {
            return response()->json(['date' => $date, 'distance_m' => 0, 'shifts' => [], 'points' => []]);
        }

        $sessionIndex = [];
        $shiftDistances = [];
        $shiftBounds = [];

        $points = $rows->map(function ($point) use (&$sessionIndex, &$shiftDistances, &$shiftBounds) {
            $recordedAt = CarbonImmutable::parse($point->recorded_at, 'UTC');
            $key = $point->session_id ?? 'none';

            if (! array_key_exists($key, $sessionIndex)) {
                $shiftIndex = count($sessionIndex);
                $sessionIndex[$key] = $shiftIndex;
                $shiftDistances[$shiftIndex] = 0.0;
                $shiftBounds[$shiftIndex] = ['start' => $recordedAt, 'end' => $recordedAt];
            }
            $shiftIndex = $sessionIndex[$key];
            $shiftDistances[$shiftIndex] += (float) $point->distance_m;
            $shiftBounds[$shiftIndex]['end'] = $recordedAt;

            return [
                'lng' => (float) $point->lng,
                'lat' => (float) $point->lat,
                'distance_m' => (float) $point->distance_m,
                'accuracy_m' => $point->accuracy_m === null ? null : (float) $point->accuracy_m,
                'speed_mps' => $point->speed_mps === null ? null : (float) $point->speed_mps,
                'heading_deg' => $point->heading_deg === null ? null : (float) $point->heading_deg,
                'battery_pct' => $point->battery_pct === null ? null : (int) $point->battery_pct,
                'recorded_at' => $recordedAt->toISOString(),
                'shift_index' => $shiftIndex,
            ];
        });

        $summary = DB::table('location_points')
            ->where('employee_id', $employee->id)
            ->whereBetween('recorded_at', [$startAt, $endAt])
            ->selectRaw('SUM(distance_m) AS distance_m, AVG(speed_mps) AS average_speed_mps, MAX(speed_mps) AS max_speed_mps, AVG(accuracy_m) AS average_accuracy_m, MIN(recorded_at) AS first_point_at, MAX(recorded_at) AS last_point_at, COUNT(*) AS points_count')
            ->first();

        $shifts = collect($shiftBounds)->map(fn ($bounds, $index) => [
            'index' => $index,
            'start' => $bounds['start']->toISOString(),
            'end' => $bounds['end']->toISOString(),
            'label' => $bounds['start']->setTimezone($timezone)->format('H:i').'–'.$bounds['end']->setTimezone($timezone)->format('H:i'),
            'distance_m' => $shiftDistances[$index],
        ])->values();

        return response()->json([
            'date' => $date,
            'start' => $startAt->toISOString(),
            'end' => $endAt->toISOString(),
            'distance_m' => (float) ($summary->distance_m ?? 0),
            'average_speed_mps' => $summary->average_speed_mps === null ? null : (float) $summary->average_speed_mps,
            'max_speed_mps' => $summary->max_speed_mps === null ? null : (float) $summary->max_speed_mps,
            'average_accuracy_m' => $summary->average_accuracy_m === null ? null : (float) $summary->average_accuracy_m,
            'first_point_at' => $summary->first_point_at,
            'last_point_at' => $summary->last_point_at,
            'points_count' => (int) ($summary->points_count ?? 0),
            'shifts' => $shifts,
            'points' => $this->decimatePoints($points)->values(),
        ]);
    }

    /**
     * A busy day at a 10s heartbeat can produce thousands of points. Distance
     * aggregates (`$shiftDistances`, `$summary`) are computed above from the full
     * set before this runs, so decimation here only thins what gets rendered on
     * the trail map — nearby points collapse into one, per shift so a shift's
     * first/last point (the map's start/end markers) is never thinned away.
     *
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $points
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function decimatePoints($points, float $minMeters = 12.0, int $hardCapPerShift = 400)
    {
        return $points
            ->groupBy('shift_index')
            ->flatMap(function ($group) use ($minMeters, $hardCapPerShift) {
                $group = $group->values();
                if ($group->count() <= 2) {
                    return $group;
                }

                $kept = collect([$group->first()]);
                foreach ($group->slice(1, -1) as $point) {
                    $lastKept = $kept->last();
                    $distance = $this->haversineMeters($lastKept['lat'], $lastKept['lng'], $point['lat'], $point['lng']);
                    if ($distance >= $minMeters) {
                        $kept->push($point);
                    }
                }
                $kept->push($group->last());

                if ($kept->count() <= $hardCapPerShift) {
                    return $kept;
                }

                $stride = (int) ceil($kept->count() / $hardCapPerShift);

                return $kept->values()->filter(fn ($point, $index) => $index % $stride === 0 || $index === $kept->count() - 1);
            })
            ->sortBy('recorded_at');
    }

    private function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $h = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $earthRadius * asin(min(1.0, sqrt($h)));
    }

    public function index(Request $request, User $employee): JsonResponse
    {
        $this->audit->record($request->user(), $employee->id, AccessAuditAction::ViewTrail, $request->ip());
        $timezone = config('tracking.timezone');
        $from = CarbonImmutable::now()->utc()->subDays(config('tracking.retention_days'));

        $rows = DB::table('location_points')
            ->where('employee_id', $employee->id)
            ->where('recorded_at', '>=', $from)
            ->selectRaw('DATE(recorded_at AT TIME ZONE ?) AS date, SUM(distance_m) AS distance_m, MIN(recorded_at) AS started_at, MAX(recorded_at) AS ended_at, COUNT(*) AS points_count', [$timezone])
            ->groupBy('date')
            ->orderByDesc('date')
            ->get();

        return response()->json($rows);
    }
}
