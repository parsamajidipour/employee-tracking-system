<?php

namespace App\Services;

use App\Enums\TrackingSessionEndReason;
use App\Models\TrackingSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * The single place a tracking_sessions row gets opened or closed. A session
 * opens when the first accepted point arrives for an employee with no open
 * (ended_at IS NULL) session — see App\Services\TrackingGate, the only
 * caller of openOrReuse(). closeEndedSessions() is the other half, called by
 * the tracking:close-ended-sessions scheduled command.
 *
 * "At most one open session per employee" is enforced twice: here (find
 * before create) and in the database (a partial unique index on
 * (employee_id) WHERE ended_at IS NULL, in the create_tracking_sessions_table
 * migration). The database is the real guarantee — openOrReuse() re-reads on
 * a unique-violation instead of trying to prevent the race itself, since two
 * concurrent accepted batches for the same employee is the only way that
 * race could happen and locking around it here wouldn't be simpler than
 * just losing the race gracefully.
 */
final class TrackingSessionManager
{
    private const UNIQUE_VIOLATION_SQLSTATE = '23505';

    public function __construct(private readonly ShiftWindowResolver $resolver)
    {
    }

    public function openOrReuse(User $employee, CarbonInterface $startedAt): TrackingSession
    {
        $open = $this->findOpen($employee);
        if ($open !== null) {
            return $open;
        }

        try {
            // Wrapped in its own transaction so a caught unique-violation
            // below only rolls back this savepoint, not the whole request's
            // transaction — Postgres aborts an entire transaction on any
            // error inside it (unlike MySQL), so without this, the conflict
            // path would leave the connection unusable for whatever runs
            // next (visible under RefreshDatabase's per-test wrapping
            // transaction, but exactly as real under any transaction a
            // caller might already be in). Same pattern as
            // App\Services\DeviceService::login().
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
     * Closes every open session whose window has ended as of $now, with
     * end_reason=window_closed. Idempotent: only ever selects sessions with
     * ended_at IS NULL, so a session already closed by an earlier run is
     * never touched again — running this twice, or after a long delay,
     * produces the same end state as running it once on time.
     *
     * "Safe to run late" also covers what ended_at gets set to: not $now
     * (which could be hours after the window actually ended, if this job
     * itself ran late), but the effective end of whichever window governed
     * the session's started_at — the same resolver everything else in this
     * app uses (CLAUDE.md invariant 8), never a second implementation of
     * "when did this window end."
     *
     * @return int number of sessions closed
     */
    public function closeEndedSessions(CarbonInterface $now): int
    {
        $now = CarbonImmutable::instance($now)->utc();
        $closed = 0;

        $openSessions = TrackingSession::whereNull('ended_at')->with('employee')->get();

        foreach ($openSessions as $session) {
            if ($this->resolver->resolve($session->employee, $now) !== null) {
                continue; // still inside a window right now — not ended
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
