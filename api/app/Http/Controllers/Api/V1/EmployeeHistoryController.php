<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AccessAuditAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccessAuditLogger;
use App\Services\ShiftWindowResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeHistoryController extends Controller
{
    public function __construct(private readonly AccessAuditLogger $audit) {}

    public function trail(Request $request, User $employee, ShiftWindowResolver $resolver): JsonResponse
    {
        $validated = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $date = $validated['date'] ?? CarbonImmutable::now(config('tracking.timezone'))->toDateString();
        $windows = $resolver->resolveAllForDate($employee, $date);

        $this->audit->record($request->user(), $employee->id, AccessAuditAction::ViewTrail, $request->ip());

        if ($windows->isEmpty()) {
            return response()->json(['date' => $date, 'distance_m' => 0, 'points' => []]);
        }

        $start = $windows->min(fn ($window) => $window->effectiveStart()->getTimestamp());
        $end = $windows->max(fn ($window) => $window->effectiveEnd()->getTimestamp());
        $startAt = CarbonImmutable::createFromTimestampUTC($start);
        $endAt = CarbonImmutable::createFromTimestampUTC($end);

        $points = DB::table('location_points')
            ->where('employee_id', $employee->id)
            ->whereBetween('recorded_at', [$startAt, $endAt])
            ->orderBy('recorded_at')
            ->selectRaw('ST_X(location::geometry) AS lng, ST_Y(location::geometry) AS lat, distance_m, accuracy_m, speed_mps, heading_deg, battery_pct, recorded_at')
            ->get()
            ->map(fn ($point) => [
                'lng' => (float) $point->lng,
                'lat' => (float) $point->lat,
                'distance_m' => (float) $point->distance_m,
                'accuracy_m' => $point->accuracy_m === null ? null : (float) $point->accuracy_m,
                'speed_mps' => $point->speed_mps === null ? null : (float) $point->speed_mps,
                'heading_deg' => $point->heading_deg === null ? null : (float) $point->heading_deg,
                'battery_pct' => $point->battery_pct === null ? null : (int) $point->battery_pct,
                'recorded_at' => CarbonImmutable::parse($point->recorded_at, 'UTC')->toISOString(),
            ]);

        $summary = DB::table('location_points')
            ->where('employee_id', $employee->id)
            ->whereBetween('recorded_at', [$startAt, $endAt])
            ->selectRaw('SUM(distance_m) AS distance_m, AVG(speed_mps) AS average_speed_mps, MAX(speed_mps) AS max_speed_mps, AVG(accuracy_m) AS average_accuracy_m, MIN(recorded_at) AS first_point_at, MAX(recorded_at) AS last_point_at, COUNT(*) AS points_count')
            ->first();

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
            'points' => $points,
        ]);
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
