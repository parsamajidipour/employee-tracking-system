<?php

namespace App\Services;

use App\Events\EmployeePositionUpdated;
use App\Models\User;
use App\ValueObjects\ShiftWindow;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * The single place `last_known:{employee_id}` gets written and the
 * `positions` channel gets a delta — called once per process() call (i.e.
 * per batch, not per point) from App\Services\TrackingGate, with only that
 * batch's newest accepted point.
 */
final class PositionPublisher
{
    private const REDIS_KEY_EXPIRY_BUFFER_MINUTES = 5;

    /**
     * @param  array{lat: float, lng: float, accuracy_m: ?float, battery_pct: ?int}  $point
     */
    public function publish(User $employee, array $point, CarbonImmutable $recordedAt, ShiftWindow $window): void
    {
        $updated = $this->writeLastKnown($employee, $point, $recordedAt, $window);

        // A batch that lost the race against a newer one already cached
        // (the mobile client's offline queue delivering a backlog after a
        // fresher point already arrived) must not push a delta either —
        // the same "never move the marker backwards" rule applies to the
        // live feed as to the cached snapshot.
        if ($updated) {
            $this->broadcast($employee, $point, $recordedAt, $window);
        }
    }

    /**
     * Belt-and-suspenders, not the primary guarantee: GET /api/v1/positions
     * (App\Http\Controllers\Api\V1\PositionController) re-checks
     * ShiftWindowResolver at read time before returning anyone, which is
     * what actually keeps a stale position from ever being served. This TTL
     * only keeps the Redis key itself from lingering forever if that read
     * path never runs (e.g. no one loads the map again before the next
     * shift) — a shortly-after-window-end expiry, not the thing enforcing
     * "no location data outside a shift window."
     *
     * Only ever moves last_known forward: the mobile client's offline queue
     * means an older batch can be delivered after a newer one (a reconnect
     * flushing a backlog after already having sent something fresher), and
     * writing it unconditionally would snap the map marker back to a stale
     * position. recorded_at is stored alongside lat/lng specifically so this
     * comparison has something to check against.
     *
     * @param  array{lat: float, lng: float, accuracy_m: ?float, battery_pct: ?int}  $point
     * @return bool whether the key was actually written
     */
    private function writeLastKnown(User $employee, array $point, CarbonImmutable $recordedAt, ShiftWindow $window): bool
    {
        $key = "last_known:{$employee->id}";
        $existing = Redis::get($key);

        if ($existing !== null) {
            $existingRecordedAt = CarbonImmutable::parse(json_decode($existing, true)['recorded_at']);

            if ($existingRecordedAt->greaterThanOrEqualTo($recordedAt)) {
                return false;
            }
        }

        $payload = [
            'employee_id' => $employee->id,
            'name' => $employee->name,
            'team_name' => $employee->team?->name,
            'lat' => $point['lat'],
            'lng' => $point['lng'],
            'accuracy_m' => $point['accuracy_m'] ?? null,
            'battery_pct' => $point['battery_pct'] ?? null,
            'recorded_at' => $recordedAt->toISOString(),
            'effective_end' => $window->effectiveEnd()->toISOString(),
        ];

        $secondsUntilWindowEnds = $window->effectiveEnd()->getTimestamp() - CarbonImmutable::now()->getTimestamp();
        $ttlSeconds = max(1, $secondsUntilWindowEnds + self::REDIS_KEY_EXPIRY_BUFFER_MINUTES * 60);

        Redis::setex($key, $ttlSeconds, json_encode($payload));

        return true;
    }

    /**
     * A broadcast-server outage must never fail a mobile employee's point
     * submission. Storing the point correctly is the guarantee this system
     * exists for (CLAUDE.md); the live-map push is a real-time convenience
     * layered on top of that, not the source of truth — so a failure here
     * is logged and swallowed, never allowed to bubble up into the /track
     * response.
     *
     * @param  array{lat: float, lng: float, accuracy_m: ?float, battery_pct: ?int}  $point
     */
    private function broadcast(User $employee, array $point, CarbonImmutable $recordedAt, ShiftWindow $window): void
    {
        try {
            broadcast(new EmployeePositionUpdated(
                $employee->id,
                $employee->name,
                $employee->team?->name,
                $point['lat'],
                $point['lng'],
                $point['accuracy_m'] ?? null,
                $point['battery_pct'] ?? null,
                $recordedAt->toISOString(),
                $window->effectiveEnd()->toISOString(),
            ));
        } catch (Throwable $e) {
            Log::warning('Failed to broadcast position update.', [
                'employee_id' => $employee->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
