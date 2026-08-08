<?php

namespace Database\Seeders;

use App\Enums\AccessAuditAction;
use App\Enums\TrackingSessionEndReason;
use App\Enums\UserRole;
use App\Models\Device;
use App\Models\TrackingSession;
use App\Models\User;
use App\Services\AccessAuditLogger;
use App\Services\ShiftWindowResolver;
use App\ValueObjects\ShiftWindow;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TrackingDemoSeeder extends Seeder
{
    private const POINTS_PER_SESSION = 12;

    private const LOOKBACK_DAYS = 7;

    private const UNPAIRED_EMPLOYEES = 2;

    private const SYNTHETIC_ORIGIN_LAT = 23.55;

    private const SYNTHETIC_ORIGIN_LNG = 58.35;

    public function __construct(
        private readonly ShiftWindowResolver $resolver,
        private readonly AccessAuditLogger $auditLogger,
    ) {}

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('TrackingDemoSeeder: skipped in production.');

            return;
        }

        $now = CarbonImmutable::now();
        $supervisor = User::where('role', UserRole::Supervisor)->first();
        $employees = User::employees()->active()->orderBy('id')->get();

        foreach ($employees as $index => $employee) {
            if ($index >= self::UNPAIRED_EMPLOYEES) {
                Device::updateOrCreate(
                    ['employee_id' => $employee->id, 'device_identifier' => 'seed-device-'.$employee->id],
                    ['device_name' => 'Demo Handset '.($index + 1), 'last_seen_at' => $now, 'revoked_at' => null],
                );
            }

            if ($supervisor !== null) {
                $this->auditLogger->record($supervisor, $employee->id, AccessAuditAction::ViewLivePosition, '127.0.0.1');
            }

            $window = $this->mostRecentStartedWindow($employee, $now);

            if ($window === null) {
                continue;
            }

            $startedAt = $window->effectiveStart();
            $endsAt = $window->effectiveEnd()->min($now);

            if ($startedAt->greaterThanOrEqualTo($endsAt)) {
                continue;
            }

            $isOpen = $window->effectiveEnd()->greaterThan($now);

            $session = TrackingSession::firstOrCreate(
                ['employee_id' => $employee->id, 'started_at' => $startedAt],
                [
                    'ended_at' => $isOpen ? null : $window->effectiveEnd(),
                    'end_reason' => $isOpen ? null : TrackingSessionEndReason::WindowClosed,
                ],
            );

            $this->seedPoints($employee->id, $session->id, $startedAt, $endsAt, $index);
        }
    }

    private function mostRecentStartedWindow(User $employee, CarbonImmutable $now): ?ShiftWindow
    {
        $localNow = $now->setTimezone(config('tracking.timezone'));

        for ($offset = 0; $offset <= self::LOOKBACK_DAYS; $offset++) {
            $window = $this->resolver->resolveForDate($employee, $localNow->subDays($offset)->toDateString());

            if ($window !== null && $window->effectiveStart()->lessThanOrEqualTo($now)) {
                return $window;
            }
        }

        return null;
    }

    private function seedPoints(int $employeeId, int $sessionId, CarbonImmutable $from, CarbonImmutable $to, int $index): void
    {
        $exists = DB::table('location_points')->where('session_id', $sessionId)->exists();

        if ($exists) {
            return;
        }

        $spanSeconds = max(1, $to->getTimestamp() - $from->getTimestamp());
        $step = intdiv($spanSeconds, self::POINTS_PER_SESSION);

        for ($i = 0; $i < self::POINTS_PER_SESSION; $i++) {
            $recordedAt = $from->addSeconds($step * $i);
            $lat = self::SYNTHETIC_ORIGIN_LAT + ($index * 0.004) + ($i * 0.0007);
            $lng = self::SYNTHETIC_ORIGIN_LNG + ($index * 0.005) + (sin($i / 2) * 0.0009);

            DB::statement(
                'INSERT INTO location_points (session_id, employee_id, location, accuracy_m, speed_mps, heading_deg, battery_pct, is_mocked, recorded_at, received_at, created_at, updated_at)
                 VALUES (?, ?, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?, ?, ?, ?, false, ?, ?, ?, ?)',
                [
                    $sessionId,
                    $employeeId,
                    $lng,
                    $lat,
                    8 + ($i % 5),
                    1.2 + ($i % 3) * 0.4,
                    ($i * 27) % 360,
                    100 - $i,
                    $recordedAt,
                    $recordedAt,
                    $recordedAt,
                    $recordedAt,
                ],
            );
        }
    }
}
