<?php

namespace App\Services;

use App\Enums\CaseStatus;
use App\Models\InspectionCase;
use App\Models\User;
use App\ValueObjects\SurveyorCandidate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

final class CaseAssignmentService
{
    public function __construct(private readonly ShiftWindowResolver $resolver) {}

    /**
     * @return Collection<int, SurveyorCandidate>
     */
    public function rank(InspectionCase $case): Collection
    {
        $now = CarbonImmutable::now();

        $openCounts = InspectionCase::query()
            ->whereNotNull('assigned_to')
            ->whereIn('status', array_map(fn (CaseStatus $s) => $s->value, CaseStatus::open()))
            ->selectRaw('assigned_to, count(*) as open_count')
            ->groupBy('assigned_to')
            ->pluck('open_count', 'assigned_to');

        return User::query()
            ->employees()
            ->active()
            ->get()
            ->filter(fn (User $employee) => $this->resolver->resolve($employee, $now) !== null)
            ->map(function (User $employee) use ($case, $openCounts) {
                $raw = Redis::get("last_known:{$employee->id}");
                if ($raw === null) {
                    return null;
                }

                $cached = json_decode($raw, true);
                if (! is_array($cached) || ! isset($cached['lat'], $cached['lng'], $cached['recorded_at'])) {
                    return null;
                }

                $lat = filter_var($cached['lat'], FILTER_VALIDATE_FLOAT);
                $lng = filter_var($cached['lng'], FILTER_VALIDATE_FLOAT);
                if ($lat === false || $lng === false || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                    return null;
                }

                $recordedAt = CarbonImmutable::parse($cached['recorded_at']);
                $online = $recordedAt->greaterThanOrEqualTo(
                    CarbonImmutable::now()->subSeconds(config('tracking.online_threshold_seconds')),
                );

                $distance = (float) DB::selectOne(
                    'SELECT ST_Distance(location, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography) AS meters FROM inspection_cases WHERE id = ?',
                    [$lng, $lat, $case->id],
                )->meters;

                return new SurveyorCandidate(
                    employeeId: $employee->id,
                    name: $employee->name,
                    lat: $lat,
                    lng: $lng,
                    distanceM: $distance,
                    openCaseCount: (int) ($openCounts[$employee->id] ?? 0),
                    connectionStatus: $online ? 'online' : 'offline',
                    recordedAt: $cached['recorded_at'],
                );
            })
            ->filter()
            ->sort(fn (SurveyorCandidate $a, SurveyorCandidate $b) => $a->distanceM <=> $b->distanceM ?: $a->openCaseCount <=> $b->openCaseCount)
            ->values();
    }
}
