<?php

namespace App\Services;

use App\Models\LocationPoint;
use App\Models\TrackingSession;
use App\Models\User;
use App\ValueObjects\TrackingGateResult;
use App\ValueObjects\ShiftWindow;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

final class TrackingGate
{
    private const MAX_POINT_AGE_HOURS = 48;

    public function __construct(
        private readonly ShiftWindowResolver $resolver,
        private readonly TrackingSessionManager $sessions,
        private readonly PositionPublisher $positions,
    ) {}

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

            $this->store($employee, $point, $recordedAt, $receivedAt, $session, $window);
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

    private function store(User $employee, array $point, CarbonImmutable $recordedAt, CarbonImmutable $receivedAt, TrackingSession $session, ShiftWindow $window): void
    {
        $previous = DB::selectOne(
            'SELECT ST_Y(location::geometry) AS lat, ST_X(location::geometry) AS lng, accuracy_m FROM location_points WHERE employee_id = ? AND recorded_at >= ? AND recorded_at < ? ORDER BY recorded_at DESC LIMIT 1',
            [$employee->id, $window->effectiveStart(), $recordedAt],
        );
        $distance = $previous === null ? 0.0 : $this->segmentDistance($previous, $point);

        LocationPoint::create([
            'session_id' => $session->id,
            'employee_id' => $employee->id,
            'location' => new Expression(sprintf(
                'ST_SetSRID(ST_MakePoint(%s, %s), 4326)::geography',
                $this->floatLiteral($point['lng']),
                $this->floatLiteral($point['lat']),
            )),
            'distance_m' => $distance,
            'accuracy_m' => $point['accuracy_m'] ?? null,
            'speed_mps' => $point['speed_mps'] ?? null,
            'heading_deg' => $point['heading_deg'] ?? null,
            'battery_pct' => $point['battery_pct'] ?? null,
            'is_mocked' => (bool) $point['is_mocked'],
            'recorded_at' => $recordedAt,
            'received_at' => $receivedAt,
        ]);
    }

    private function segmentDistance(object $previous, array $point): float
    {
        $raw = (float) DB::selectOne(
            'SELECT ST_Distance(ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography) AS meters',
            [$previous->lng, $previous->lat, $point['lng'], $point['lat']],
        )->meters;

        $noiseFloor = max(
            (float) config('tracking.stationary_noise_floor_m'),
            (float) ($previous->accuracy_m ?? 0),
            (float) ($point['accuracy_m'] ?? 0),
        );

        return $raw <= $noiseFloor ? 0.0 : $raw;
    }

    private function floatLiteral(mixed $value): string
    {
        return sprintf('%.8F', (float) $value);
    }
}
