<?php

namespace App\Services;

use App\Enums\TrackingSessionEndReason;
use App\Models\TrackingSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class TrackingSessionManager
{
    private const UNIQUE_VIOLATION_SQLSTATE = '23505';

    public function __construct(private readonly ShiftWindowResolver $resolver) {}

    public function openOrReuse(User $employee, CarbonInterface $startedAt): TrackingSession
    {
        $open = $this->findOpen($employee);
        if ($open !== null) {
            return $open;
        }

        try {
            return DB::transaction(fn () => TrackingSession::create([
                'employee_id' => $employee->id,
                'started_at' => $startedAt,
            ]));
        } catch (QueryException $e) {
            if (($e->errorInfo[0] ?? null) !== self::UNIQUE_VIOLATION_SQLSTATE) {
                throw $e;
            }

            return $this->findOpen($employee) ?? throw $e;
        }
    }

    /**
     * @return int number of sessions closed
     */
    public function closeEndedSessions(CarbonInterface $now): int
    {
        $now = CarbonImmutable::instance($now)->utc();
        $closed = 0;

        $openSessions = TrackingSession::whereNull('ended_at')->with('employee')->get();

        foreach ($openSessions as $session) {
            if ($this->resolver->resolve($session->employee, $now) !== null) {
                continue;
            }

            $window = $this->resolver->resolve($session->employee, $session->started_at);
            $endedAt = $window !== null ? $window->effectiveEnd()->min($now) : $now;

            $session->update([
                'ended_at' => $endedAt,
                'end_reason' => TrackingSessionEndReason::WindowClosed,
            ]);
            $closed++;
        }

        return $closed;
    }

    private function findOpen(User $employee): ?TrackingSession
    {
        return TrackingSession::where('employee_id', $employee->id)->whereNull('ended_at')->first();
    }
}
