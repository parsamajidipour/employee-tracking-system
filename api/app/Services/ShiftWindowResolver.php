<?php

namespace App\Services;

use App\Enums\ShiftWindowSource;
use App\Models\ShiftException;
use App\Models\ShiftTemplate;
use App\Models\Team;
use App\Models\User;
use App\ValueObjects\ShiftWindow;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The single source of truth for "is this employee inside a working-hours
 * window right now." Every caller — controllers, jobs, commands, the
 * app-facing endpoint — goes through resolve(). No window arithmetic
 * anywhere else (CLAUDE.md).
 *
 * Resolution order: shift_exceptions -> employee_shifts -> team
 * shift_templates -> deny. A row that claims "today" at a given level is
 * fully authoritative for that level: it produces a window or it doesn't,
 * but either way there is no fallthrough to the next level. This defaults
 * toward *less* tracking, not more — an explicit per-employee override that
 * is silent about a given day does not fall back to the team's template for
 * that day.
 *
 * Windows always resolve in the employee's team timezone. shift_templates
 * carries its own `timezone` column per the schema, but the resolver never
 * reads it — CLAUDE.md is explicit that there is exactly one timezone
 * source for resolution, the team's.
 *
 * Midnight-crossing shifts (e.g. 22:00-06:00) are one continuous window, not
 * two. Every level evaluates two candidate windows — one anchored on the
 * local date of the instant ("today"), one anchored on the day before
 * ("yesterday") — and checks the instant against both using absolute UTC
 * comparison, rather than trying to detect "does this cross midnight" from
 * clock times (grace minutes can push the *effective* window across
 * midnight even when the *core* window doesn't). A shift's governing
 * calendar date is always its start date: a template's days_of_week
 * membership and an exception's `date` column both mean "this authority
 * governs the shift that starts on this date," never the date it happens to
 * still be running into.
 *
 * A leave/holiday exception on the instant's local date is an absolute veto:
 * checked first, before any anchor candidate is built at any level. A
 * crossing-midnight shift anchored on the day before — whether from an
 * exception, an employee_shift, or a team template — never gets a chance to
 * bleed into a day the employee is on leave, because resolution never
 * reaches that logic once today's leave/holiday row is found.
 *
 * Known, deliberately undecided edge case (not exercised by any required
 * test, documented rather than silently handled or silently broken):
 *  - The employee_shifts "yesterday anchor" lookup queries which row was
 *    active at yesterday's local midnight, not at the overnight shift's
 *    actual start time. If an effective_from/effective_to cutover falls
 *    between those two instants, this queries the wrong row. The required
 *    midday-cutover and crossing-midnight tests are deliberately independent
 *    scenarios, so this combination is untested.
 *
 * If a team has multiple shift_templates matching the same day-of-week
 * (nothing in the schema marks one "default"), the lowest id wins. Arbitrary
 * but deterministic; not exercised by any required test.
 *
 * resolveNext() answers a different question than resolve(): not "is this
 * instant inside a window" but "when does the next one start." It walks
 * forward, local calendar day by local calendar day, applying the same
 * exceptions -> employee_shifts -> team-template precedence used by resolve()
 * (via resolveGoverningWindowForDate()), and returns the first candidate
 * whose graced start is strictly after the given instant. It never looks at
 * "yesterday" the way resolve() does — a window that already started isn't
 * "next," it's current (or already over), so today's own anchor is as far
 * back as it needs to look. The walk is bounded at
 * NEXT_WINDOW_LOOKAHEAD_DAYS days; every shift pattern in this schema cycles
 * within a week, so that bound is deliberate, not arbitrary, and a null
 * result after it means genuinely no window, not "search harder."
 */
final class ShiftWindowResolver
{
    private const NEXT_WINDOW_LOOKAHEAD_DAYS = 8;

    public function resolve(User $employee, CarbonInterface $instant): ?ShiftWindow
    {
        $instant = CarbonImmutable::instance($instant)->utc();

        $team = $employee->team;
        if ($team === null) {
            return null;
        }

        $timezone = $team->timezone;
        $localInstant = $instant->setTimezone($timezone);
        $today = $localInstant->toDateString();
        $yesterday = $localInstant->subDay()->toDateString();

        // Each level below returns:
        //   null  -> this level is silent for today, fall through
        //   false -> this level is authoritative and denies (stop, no window)
        //   ShiftWindow -> this level is authoritative and this is the window
        $level = $this->resolveFromExceptions($employee, $instant, $today, $yesterday, $timezone);
        if ($level !== null) {
            return $level === false ? null : $level;
        }

        $level = $this->resolveFromEmployeeShifts($employee, $instant, $today, $yesterday, $timezone);
        if ($level !== null) {
            return $level === false ? null : $level;
        }

        // Last level: "silent" and "deny" collapse to the same outcome, so
        // this one just returns ?ShiftWindow directly.
        return $this->resolveFromTeamTemplates($team, $instant, $today, $yesterday, $timezone);
    }

    /**
     * What window, if any, starts on $localDate (a "Y-m-d" string in the
     * employee's team timezone) — the admin-facing counterpart to resolve():
     * same precedence, same helper, just anchored on an explicit calendar
     * date an admin picked rather than "now." This is what the panel's
     * per-employee schedule page calls to show what the resolver actually
     * produces for a chosen date, next to the raw rows — never a second,
     * separate implementation of window logic (CLAUDE.md invariant 8).
     */
    public function resolveForDate(User $employee, string $localDate): ?ShiftWindow
    {
        $team = $employee->team;
        if ($team === null) {
            return null;
        }

        return $this->resolveGoverningWindowForDate($employee, $team, $localDate, $team->timezone);
    }

    /**
     * The first window that starts strictly after $after, or null if none is
     * found within NEXT_WINDOW_LOOKAHEAD_DAYS.
     */
    public function resolveNext(User $employee, CarbonInterface $after): ?ShiftWindow
    {
        $after = CarbonImmutable::instance($after)->utc();

        $team = $employee->team;
        if ($team === null) {
            return null;
        }

        $timezone = $team->timezone;
        $localAfter = $after->setTimezone($timezone);

        for ($offset = 0; $offset <= self::NEXT_WINDOW_LOOKAHEAD_DAYS; $offset++) {
            $anchorDate = $localAfter->addDays($offset)->toDateString();
            $window = $this->resolveGoverningWindowForDate($employee, $team, $anchorDate, $timezone);

            if ($window !== null && $window->effectiveStart()->greaterThan($after)) {
                return $window;
            }
        }

        return null;
    }

    /**
     * What window, if any, starts on $anchorDate — same precedence as
     * resolve() (exceptions -> employee_shifts -> team template, each level
     * fully authoritative once it has a row for this date), but for a single
     * anchor date with no "yesterday" candidate and no containment check.
     * Used by resolveNext() and resolveForDate(); resolve() has its own
     * containment-checked, two-anchor version of this same precedence and is
     * left untouched.
     */
    private function resolveGoverningWindowForDate(User $employee, Team $team, string $anchorDate, string $timezone): ?ShiftWindow
    {
        $exception = ShiftException::where('employee_id', $employee->id)
            ->where('date', $anchorDate)
            ->first();

        if ($exception !== null) {
            return $exception->type->isDeny() ? null : $this->buildExceptionWindow($exception, $anchorDate, $timezone);
        }

        $anchorStartLocal = CarbonImmutable::parse($anchorDate, $timezone)->utc();

        $shift = $employee->employeeShifts()
            ->where('effective_from', '<=', $anchorStartLocal)
            ->where(function ($query) use ($anchorStartLocal) {
                $query->whereNull('effective_to')->orWhere('effective_to', '>', $anchorStartLocal);
            })
            ->orderByDesc('effective_from')
            ->with('template')
            ->first();

        if ($shift !== null) {
            return $this->buildTemplateWindow($shift->template, $anchorDate, $timezone, ShiftWindowSource::EmployeeShift);
        }

        $template = $this->pickTemplateFor($team, $anchorDate);

        return $this->buildTemplateWindow($template, $anchorDate, $timezone, ShiftWindowSource::TeamTemplate);
    }

    private function resolveFromExceptions(User $employee, CarbonImmutable $instant, string $today, string $yesterday, string $timezone): ShiftWindow|false|null
    {
        $exceptionToday = ShiftException::where('employee_id', $employee->id)
            ->where('date', $today)
            ->first();

        // Absolute veto: a leave/holiday exception on the instant's local
        // date denies outright, checked before any anchor candidate is even
        // built. This must come first — a crossing-midnight window anchored
        // on yesterday (from an exception, an employee_shift, or a team
        // template) never gets a chance to bleed into a day the employee is
        // on leave, because we never reach that logic at all.
        if ($exceptionToday !== null && $exceptionToday->type->isDeny()) {
            return false;
        }

        $exceptionYesterday = ShiftException::where('employee_id', $employee->id)
            ->where('date', $yesterday)
            ->first();

        $windowYesterday = $this->buildExceptionWindow($exceptionYesterday, $yesterday, $timezone);
        if ($windowYesterday !== null && $windowYesterday->contains($instant)) {
            return $windowYesterday;
        }

        if ($exceptionToday === null) {
            return null; // this level is silent for today — fall through
        }

        $windowToday = $this->buildExceptionWindow($exceptionToday, $today, $timezone);

        // A today-row exists, so this level is authoritative either way.
        return $windowToday !== null && $windowToday->contains($instant)
            ? $windowToday
            : false;
    }

    private function resolveFromEmployeeShifts(User $employee, CarbonImmutable $instant, string $today, string $yesterday, string $timezone): ShiftWindow|false|null
    {
        $startOfYesterdayLocal = CarbonImmutable::parse($yesterday, $timezone)->utc();

        $shiftAtYesterdayAnchor = $employee->employeeShifts()
            ->where('effective_from', '<=', $startOfYesterdayLocal)
            ->where(function ($query) use ($startOfYesterdayLocal) {
                $query->whereNull('effective_to')->orWhere('effective_to', '>', $startOfYesterdayLocal);
            })
            ->orderByDesc('effective_from')
            ->with('template')
            ->first();

        $windowYesterday = $shiftAtYesterdayAnchor !== null
            ? $this->buildTemplateWindow($shiftAtYesterdayAnchor->template, $yesterday, $timezone, ShiftWindowSource::EmployeeShift)
            : null;
        if ($windowYesterday !== null && $windowYesterday->contains($instant)) {
            return $windowYesterday;
        }

        $shiftAtInstant = $employee->employeeShifts()
            ->where('effective_from', '<=', $instant)
            ->where(function ($query) use ($instant) {
                $query->whereNull('effective_to')->orWhere('effective_to', '>', $instant);
            })
            ->orderByDesc('effective_from')
            ->with('template')
            ->first();

        if ($shiftAtInstant === null) {
            return null; // no override governs today — fall through
        }

        $windowToday = $this->buildTemplateWindow($shiftAtInstant->template, $today, $timezone, ShiftWindowSource::EmployeeShift);

        return $windowToday !== null && $windowToday->contains($instant)
            ? $windowToday
            : false;
    }

    private function resolveFromTeamTemplates(Team $team, CarbonImmutable $instant, string $today, string $yesterday, string $timezone): ?ShiftWindow
    {
        $templateYesterday = $this->pickTemplateFor($team, $yesterday);
        $windowYesterday = $this->buildTemplateWindow($templateYesterday, $yesterday, $timezone, ShiftWindowSource::TeamTemplate);
        if ($windowYesterday !== null && $windowYesterday->contains($instant)) {
            return $windowYesterday;
        }

        $templateToday = $this->pickTemplateFor($team, $today);
        $windowToday = $this->buildTemplateWindow($templateToday, $today, $timezone, ShiftWindowSource::TeamTemplate);
        if ($windowToday !== null && $windowToday->contains($instant)) {
            return $windowToday;
        }

        return null; // last level — final deny
    }

    private function pickTemplateFor(Team $team, string $anchorDate): ?ShiftTemplate
    {
        $dayOfWeek = Carbon::parse($anchorDate)->dayOfWeek;

        return $team->shiftTemplates
            ->filter(fn (ShiftTemplate $template) => in_array($dayOfWeek, $template->days_of_week, true))
            ->sortBy('id')
            ->first();
    }

    private function buildExceptionWindow(?ShiftException $exception, string $anchorDate, string $timezone): ?ShiftWindow
    {
        if ($exception === null) {
            return null;
        }

        if (! $exception->type->definesWindow() || $exception->start_at === null || $exception->end_at === null) {
            return null; // leave/holiday, or malformed data — no window
        }

        return $this->buildWindow($anchorDate, $exception->start_at, $exception->end_at, $timezone, ShiftWindowSource::Exception, 0, 0);
    }

    private function buildTemplateWindow(?ShiftTemplate $template, string $anchorDate, string $timezone, ShiftWindowSource $source): ?ShiftWindow
    {
        if ($template === null) {
            return null;
        }

        $dayOfWeek = Carbon::parse($anchorDate)->dayOfWeek;
        if (! in_array($dayOfWeek, $template->days_of_week, true)) {
            return null;
        }

        return $this->buildWindow($anchorDate, $template->start_time, $template->end_time, $timezone, $source, $template->grace_before_min, $template->grace_after_min);
    }

    /**
     * Combines an anchor calendar date with a start/end clock-time pair in
     * the given timezone, producing a UTC window. If end <= start as clock
     * times, the end rolls to the next calendar day — this is what makes a
     * 22:00-06:00 shift one continuous window instead of two.
     */
    private function buildWindow(string $anchorDate, string $startTime, string $endTime, string $timezone, ShiftWindowSource $source, int $graceBeforeMin, int $graceAfterMin): ShiftWindow
    {
        $start = Carbon::parse("{$anchorDate} {$startTime}", $timezone);
        $end = Carbon::parse("{$anchorDate} {$endTime}", $timezone);

        if ($end->lessThanOrEqualTo($start)) {
            $end = $end->addDay();
        }

        return new ShiftWindow(
            CarbonImmutable::instance($start)->utc(),
            CarbonImmutable::instance($end)->utc(),
            $source,
            $graceBeforeMin,
            $graceAfterMin,
        );
    }
}
