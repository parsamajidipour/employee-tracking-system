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
     * Ranks currently-on-shift employees by straight-line distance to the case's
     * property location, tie-broken by current open workload. Uses each
     * employee's last known live position (the same Redis cache the live map
     * reads), so no separate routing/geocoding infrastructure is required —
     * this project runs on a single VPS at a few dozen employees, and a
     * straight-line distance is precise enough to pick "who is already in
     * Quriyat" without standing up OSRM.
     *
     * @return Collection<int, SurveyorCandidate>
     */
    public function rank(InspectionCase $case): Collection
    {
        $now = CarbonImmutable::now();
        $caseLat = $case->lat;
        $caseLng = $case->lng;

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
            ->map(function (User $employee) use ($caseLat, $caseLng, $openCounts) {
                $raw = Redis::get("last_known:{$employee->id}");
                if ($raw === null) {
                    return null;
                }

                $cached = json_decode($raw, true);
                $recordedAt = CarbonImmutable::parse($cached['recorded_at']);
                $online = $recordedAt->greaterThanOrEqualTo(
                    CarbonImmutable::now()->subSeconds(config('tracking.online_threshold_seconds')),
                );

                $distance = (float) DB::selectOne(
                    'SELECT ST_Distance(ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography) AS meters',
                    [$cached['lng'], $cached['lat'], $caseLng, $caseLat],
                )->meters;

                return new SurveyorCandidate(
                    employeeId: $employee->id,
                    name: $employee->name,
                    lat: (float) $cached['lat'],
                    lng: (float) $cached['lng'],
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
