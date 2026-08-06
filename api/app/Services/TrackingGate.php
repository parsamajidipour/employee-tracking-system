<?php

namespace App\Services;

use App\Models\LocationPoint;
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
 */
final class TrackingGate
{
    private const MAX_POINT_AGE_HOURS = 48;

    public function __construct(private readonly ShiftWindowResolver $resolver)
    {
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

        foreach ($points as $point) {
            $recordedAt = CarbonImmutable::parse($point['recorded_at'])->utc();

            if ($recordedAt->lessThan($cutoff)) {
                $rejected++;

                continue;
            }

            if ($this->resolver->resolve($employee, $recordedAt) === null) {
                $rejected++;

                continue;
            }

            $this->store($employee, $point, $recordedAt, $receivedAt);
            $accepted++;
        }

        return new TrackingGateResult($accepted, $rejected);
    }

    private function store(User $employee, array $point, CarbonImmutable $recordedAt, CarbonImmutable $receivedAt): void
    {
        LocationPoint::create([
            'session_id' => null,
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
