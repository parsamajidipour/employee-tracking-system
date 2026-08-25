<?php

namespace App\Services;

use App\Enums\CaseStatus;
use App\Models\InspectionCase;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class CaseWorkloadService
{
    public function __construct(private readonly ShiftWindowResolver $resolver) {}

    /**
     * @return array{active_cases: int, pending: int, scheduled: int, in_progress: int, overdue: int, completed_today: int, completed_week: int, completed_month: int, oldest_pending_hours: ?float}
     */
    public function summary(User $employee): array
    {
        $now = CarbonImmutable::now();
        $cases = InspectionCase::where('assigned_to', $employee->id)->get();

        $pending = $cases->where('status', CaseStatus::Pending);
        $open = $cases->filter(fn (InspectionCase $c) => $c->status->isOpen());
        $scheduled = $cases->filter(fn (InspectionCase $c) => $c->status === CaseStatus::Accepted
            && $c->planned_at !== null
            && $c->planned_at->greaterThan($now));
        $overdue = $cases->filter(fn (InspectionCase $c) => $c->status === CaseStatus::Overdue
            || ($c->status === CaseStatus::Accepted
                && $c->planned_at !== null
                && $c->planned_at->lessThan($now)));

        $oldestPending = $pending->sortBy('assigned_at')->first();

        return [
            'active_cases' => $open->count(),
            'pending' => $pending->count(),
            'scheduled' => $scheduled->count(),
            'in_progress' => $cases->where('status', CaseStatus::InProgress)->count(),
            'overdue' => $overdue->count(),
            'completed_today' => $cases->filter(fn (InspectionCase $c) => $c->status === CaseStatus::Completed && $c->completed_at?->isToday())->count(),
            'completed_week' => $cases->filter(fn (InspectionCase $c) => $c->status === CaseStatus::Completed && $c->completed_at?->greaterThanOrEqualTo($now->startOfWeek()))->count(),
            'completed_month' => $cases->filter(fn (InspectionCase $c) => $c->status === CaseStatus::Completed && $c->completed_at?->greaterThanOrEqualTo($now->startOfMonth()))->count(),
            'oldest_pending_hours' => $oldestPending?->assigned_at !== null
                ? round($oldestPending->assigned_at->diffInMinutes($now) / 60, 1)
                : null,
        ];
    }

    /**
     * Splits a working-hours window into travel / inspection / idle time using
     * the already-recorded trail (`location_points`) and each case's own
     * start/completed timestamps as the only two signals available — there is
     * no office-location concept in this system, so "office time" from the
     * original brief is folded into idle/unaccounted time instead of guessed.
     *
     * @return array{window_minutes: ?int, distance_m: float, inspection_minutes: float, travel_minutes: float, idle_minutes: float}
     */
    public function dailyActivity(User $employee, string $localDate): array
    {
        $window = $this->resolver->resolveForDate($employee, $localDate);

        if ($window === null) {
            return [
                'window_minutes' => null,
                'distance_m' => 0.0,
                'inspection_minutes' => 0.0,
                'travel_minutes' => 0.0,
                'idle_minutes' => 0.0,
            ];
        }

        $start = $window->effectiveStart();
        $end = min($window->effectiveEnd(), CarbonImmutable::now());
        $elapsedMinutes = max(0, $start->diffInMinutes($end));

        $points = DB::table('location_points')
            ->where('employee_id', $employee->id)
            ->where('recorded_at', '>=', $start)
            ->where('recorded_at', '<=', $end)
            ->orderBy('recorded_at')
            ->get(['recorded_at', 'distance_m']);

        $distanceTotal = (float) $points->sum('distance_m');

        $travelMinutes = 0.0;
        $previous = null;
        foreach ($points as $point) {
            if ($previous !== null && (float) $point->distance_m > 0) {
                $travelMinutes += CarbonImmutable::parse($previous->recorded_at)->diffInMinutes(CarbonImmutable::parse($point->recorded_at));
            }
            $previous = $point;
        }

        $inspectionMinutes = (float) InspectionCase::where('assigned_to', $employee->id)
            ->whereNotNull('started_at')
            ->where('started_at', '<=', $end)
            ->where(function ($q) use ($start) {
                $q->whereNull('completed_at')->orWhere('completed_at', '>=', $start);
            })
            ->get(['started_at', 'completed_at'])
            ->sum(function (InspectionCase $case) use ($start, $end) {
                $segmentStart = max($case->started_at, $start);
                $segmentEnd = min($case->completed_at ?? $end, $end);

                return max(0, $segmentStart->diffInMinutes($segmentEnd));
            });

        $idleMinutes = max(0.0, $elapsedMinutes - $travelMinutes - $inspectionMinutes);

        return [
            'window_minutes' => $elapsedMinutes,
            'distance_m' => round($distanceTotal, 1),
            'inspection_minutes' => round($inspectionMinutes, 1),
            'travel_minutes' => round($travelMinutes, 1),
            'idle_minutes' => round($idleMinutes, 1),
        ];
    }
}
