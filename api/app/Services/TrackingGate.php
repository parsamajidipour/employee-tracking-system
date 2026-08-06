<?php

namespace App\Services;

use App\Models\LocationPoint;
use App\Models\TrackingSession;
use App\Models\User;
use App\ValueObjects\TrackingGateResult;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Expression;

/**
 * The single place a location point gets persisted. CLAUDE.md: "A location
 * point is persisted only after passing the shift gate" — this class isn't
 * just where that rule is checked, it's where the row is written, so there
 * is no code path to create a LocationPoint that didn't go through here.
 *
 * The gate evaluates `recorded_at`, never `received_at` (CLAUDE.md invariant
 * 2) — `received_at` is stored for audit/debugging only and never
 * participates in the accept/reject decision, including the 48-hour-age
 * check, which is also based on `recorded_at`.
 *
 * Rejected points increment a counter and are discarded: never written to
 * storage, never soft-deleted, never flagged for later review (CLAUDE.md
 * invariant 4).
 *
 * Points are also never stored without a session: the first accepted point
 * for an employee with no open tracking_sessions row opens one (via
 * App\Services\TrackingSessionManager), and every point in this batch reuses
 * that same session — resolved at most once per process() call, not once
 * per point, since a batch is always for a single employee.
 *
 * Storage is per point; publishing is not. The mobile client's offline queue
 * means a batch can contain several accepted points spanning the same
 * ~30s flush window, and can itself arrive after a newer batch already has
 * (a reconnect flushing a backlog). Only the single accepted point with the
 * latest recorded_at in this batch is handed to App\Services\PositionPublisher
 * — one Redis write, one broadcast, per process() call, never one per point.
 */
final class TrackingGate
{
    private const MAX_POINT_AGE_HOURS = 48;

    public function __construct(
        private readonly ShiftWindowResolver $resolver,
        private readonly TrackingSessionManager $sessions,
        private readonly PositionPublisher $positions,
    ) {
    }

    /**
     * @param  list<array{lat: float, lng: float, accuracy_m: ?float, speed_mps: ?float, heading_deg: ?float, battery_pct: ?int, is_mocked: bool, recorded_at: string}>  $points
     */
    public function process(User $employee, array $points): TrackingGateResult
    {
        $receivedAt = CarbonImmutable::now()->utc();
        $cutoff = $receivedAt->subHours(self::MAX_POINT_AGE_HOURS);

        $accepted = 0;
        $rejected = 0;
        $session = null;
        $newest = null;

        foreach ($points as $point) {
            $recordedAt = CarbonImmutable::parse($point['recorded_at'])->utc();

            if ($recordedAt->lessThan($cutoff)) {
                $rejected++;

                continue;
            }

            $window = $this->resolver->resolve($employee, $recordedAt);
            if ($window === null) {
                $rejected++;

                continue;
            }

            $session ??= $this->sessions->openOrReuse($employee, $recordedAt);

            $this->store($employee, $point, $recordedAt, $receivedAt, $session);
            $accepted++;

            if ($newest === null || $recordedAt->greaterThan($newest['recordedAt'])) {
                $newest = ['point' => $point, 'recordedAt' => $recordedAt, 'window' => $window];
            }
        }

        if ($newest !== null) {
            $this->positions->publish(
                $employee,
                [
                    'lat' => (float) $newest['point']['lat'],
                    'lng' => (float) $newest['point']['lng'],
                    'accuracy_m' => $newest['point']['accuracy_m'] ?? null,
                    'battery_pct' => $newest['point']['battery_pct'] ?? null,
                ],
                $newest['recordedAt'],
                $newest['window'],
            );
        }

        return new TrackingGateResult($accepted, $rejected);
    }

    private function store(User $employee, array $point, CarbonImmutable $recordedAt, CarbonImmutable $receivedAt, TrackingSession $session): void
    {
        LocationPoint::create([
            'session_id' => $session->id,
            'employee_id' => $employee->id,
            'location' => new Expression(sprintf(
                'ST_SetSRID(ST_MakePoint(%s, %s), 4326)::geography',
                $this->floatLiteral($point['lng']),
                $this->floatLiteral($point['lat']),
            )),
            'accuracy_m' => $point['accuracy_m'] ?? null,
            'speed_mps' => $point['speed_mps'] ?? null,
            'heading_deg' => $point['heading_deg'] ?? null,
            'battery_pct' => $point['battery_pct'] ?? null,
            'is_mocked' => (bool) $point['is_mocked'],
            'recorded_at' => $recordedAt,
            'received_at' => $receivedAt,
        ]);
    }

    /**
     * Casting to float before formatting means this can never contain SQL
     * metacharacters, regardless of what the original request payload
     * looked like — safe to inline into the raw geography expression above
     * without binding.
     */
    private function floatLiteral(mixed $value): string
    {
        return sprintf('%.8F', (float) $value);
    }
}
