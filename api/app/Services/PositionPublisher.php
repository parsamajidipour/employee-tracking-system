<?php

namespace App\Services;

use App\Events\EmployeePositionUpdated;
use App\Models\User;
use App\ValueObjects\ShiftWindow;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use Throwable;

final class PositionPublisher
{
    private const REDIS_KEY_EXPIRY_BUFFER_MINUTES = 5;

    /**
     * @param  array{lat: float, lng: float, accuracy_m: ?float, battery_pct: ?int}  $point
     */
    public function publish(User $employee, array $point, CarbonImmutable $recordedAt, ShiftWindow $window): void
    {
        $updated = $this->writeLastKnown($employee, $point, $recordedAt, $window);

        if ($updated) {
            $this->broadcast($employee, $point, $recordedAt, $window);
        }
    }

    /**
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
            'lat' => $point['lat'],
            'lng' => $point['lng'],
            'accuracy_m' => $point['accuracy_m'] ?? null,
            'battery_pct' => $point['battery_pct'] ?? null,
            'recorded_at' => $recordedAt->toISOString(),
            'effective_end' => $window->effectiveEnd()->toISOString(),
            'distance_today_m' => (float) DB::table('location_points')
                ->where('employee_id', $employee->id)
                ->where('recorded_at', '>=', CarbonImmutable::now()->utc()->startOfDay())
                ->sum('distance_m'),
            'connection_status' => 'online',
        ];

        $secondsUntilWindowEnds = $window->effectiveEnd()->getTimestamp() - CarbonImmutable::now()->getTimestamp();
        $ttlSeconds = max(1, $secondsUntilWindowEnds + self::REDIS_KEY_EXPIRY_BUFFER_MINUTES * 60);

        Redis::setex($key, $ttlSeconds, json_encode($payload));

        return true;
    }

    /**
     * @param  array{lat: float, lng: float, accuracy_m: ?float, battery_pct: ?int}  $point
     */
    private function broadcast(User $employee, array $point, CarbonImmutable $recordedAt, ShiftWindow $window): void
    {
        try {
            broadcast(new EmployeePositionUpdated(
                $employee->id,
                $employee->name,
                $point['lat'],
                $point['lng'],
                $point['accuracy_m'] ?? null,
                $point['battery_pct'] ?? null,
                $recordedAt->toISOString(),
                $window->effectiveEnd()->toISOString(),
                (float) DB::table('location_points')
                    ->where('employee_id', $employee->id)
                    ->where('recorded_at', '>=', CarbonImmutable::now()->utc()->startOfDay())
                    ->sum('distance_m'),
            ));
        } catch (Throwable $e) {
            Log::warning('Failed to broadcast position update.', [
                'employee_id' => $employee->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
