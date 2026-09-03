<?php

namespace App\Services;

use App\Enums\ShiftWindowSource;
use App\Models\EmployeeLeave;
use App\Models\EmployeeShift;
use App\Models\ShiftException;
use App\Models\ShiftTemplate;
use App\Models\User;
use App\ValueObjects\ShiftWindow;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class ShiftWindowResolver
{
    private const NEXT_WINDOW_LOOKAHEAD_DAYS = 8;

    public function resolve(User $employee, CarbonInterface $instant): ?ShiftWindow
    {
        $instant = CarbonImmutable::instance($instant)->utc();

        if ($this->isOnLeave($employee, $instant)) {
            return null;
        }

        $timezone = $this->timezone();
        $localInstant = $instant->setTimezone($timezone);
        $today = $localInstant->toDateString();
        $yesterday = $localInstant->subDay()->toDateString();

        $level = $this->resolveFromExceptions($employee, $instant, $today, $yesterday, $timezone);
        if ($level !== null) {
            return $level === false ? null : $this->withoutLeaves($employee, $level);
        }

        $level = $this->resolveFromEmployeeShifts($employee, $instant, $today, $yesterday, $timezone);

        return $level === false ? null : $this->withoutLeaves($employee, $level);
    }

    public function resolveForDate(User $employee, string $localDate): ?ShiftWindow
    {
        return $this->withoutLeaves($employee, $this->resolveGoverningWindowForDate($employee, $localDate, $this->timezone()));
    }

    public function resolveAllForDate(User $employee, string $localDate): Collection
    {
        $timezone = $this->timezone();
        $exception = ShiftException::where('employee_id', $employee->id)->where('date', $localDate)->first();

        if ($exception !== null) {
            $window = $exception->type->isDeny() ? null : $this->buildExceptionWindow($exception, $localDate, $timezone);
            $window = $this->withoutLeaves($employee, $window);

            return $window === null ? collect() : collect([$window]);
        }

        $anchorStartLocal = CarbonImmutable::parse($localDate, $timezone)->utc();
        $anchorEndLocal = CarbonImmutable::parse($localDate, $timezone)->addDay()->utc();

        $shifts = $this->shiftsOverlappingDay($employee, $anchorStartLocal, $anchorEndLocal)->get();

        if ($shifts->isNotEmpty()) {
            return $shifts
                ->map(fn ($shift) => $this->buildTemplateWindow($shift->template, $localDate, $timezone, ShiftWindowSource::EmployeeShift))
                ->map(fn (?ShiftWindow $window) => $this->withoutLeaves($employee, $window))
                ->filter()
                ->sortBy(fn (ShiftWindow $window) => $window->effectiveStart()->getTimestamp())
                ->values();
        }

        return collect();
    }

    public function resolveNext(User $employee, CarbonInterface $after): ?ShiftWindow
    {
        $after = CarbonImmutable::instance($after)->utc();

        $timezone = $this->timezone();
        $localAfter = $after->setTimezone($timezone);

        for ($offset = 0; $offset <= self::NEXT_WINDOW_LOOKAHEAD_DAYS; $offset++) {
            $anchorDate = $localAfter->addDays($offset)->toDateString();
            $window = $this->withoutLeaves($employee, $this->resolveGoverningWindowForDate($employee, $anchorDate, $timezone));

            if ($window !== null && $window->effectiveStart()->greaterThan($after)) {
                return $window;
            }
        }

        return null;
    }

    private function isOnLeave(User $employee, CarbonImmutable $instant): bool
    {
        return EmployeeLeave::where('employee_id', $employee->id)
            ->where('starts_at', '<=', $instant)
            ->where('ends_at', '>', $instant)
            ->exists();
    }

    private function withoutLeaves(User $employee, ?ShiftWindow $window): ?ShiftWindow
    {
        if ($window === null) {
            return null;
        }

        $leaves = EmployeeLeave::where('employee_id', $employee->id)
            ->overlapping($window->effectiveStart(), $window->effectiveEnd())
            ->orderBy('starts_at')
            ->get();

        foreach ($leaves as $leave) {
            $leaveStart = CarbonImmutable::instance($leave->starts_at)->utc();
            $leaveEnd = CarbonImmutable::instance($leave->ends_at)->utc();

            $coversStart = $leaveStart->lessThanOrEqualTo($window->effectiveStart());
            $coversEnd = $leaveEnd->greaterThanOrEqualTo($window->effectiveEnd());

            if ($coversStart && $coversEnd) {
                return null;
            }

            if ($coversStart) {
                $window = $window->clippedTo($leaveEnd, $window->effectiveEnd());
            } elseif ($coversEnd) {
                $window = $window->clippedTo($window->effectiveStart(), $leaveStart);
            }

            if ($window === null) {
                return null;
            }
        }

        return $window;
    }

    private function timezone(): string
    {
        return config('tracking.timezone');
    }

    private function resolveGoverningWindowForDate(User $employee, string $anchorDate, string $timezone): ?ShiftWindow
    {
        $exception = ShiftException::where('employee_id', $employee->id)
            ->where('date', $anchorDate)
            ->first();

        if ($exception !== null) {
            return $exception->type->isDeny() ? null : $this->buildExceptionWindow($exception, $anchorDate, $timezone);
        }

        $anchorStartLocal = CarbonImmutable::parse($anchorDate, $timezone)->utc();
        $anchorEndLocal = CarbonImmutable::parse($anchorDate, $timezone)->addDay()->utc();
        $shift = $this->shiftsOverlappingDay($employee, $anchorStartLocal, $anchorEndLocal)->first();

        if ($shift !== null) {
            return $this->buildTemplateWindow($shift->template, $anchorDate, $timezone, ShiftWindowSource::EmployeeShift);
        }

        return null;
    }

    private function resolveFromExceptions(User $employee, CarbonImmutable $instant, string $today, string $yesterday, string $timezone): ShiftWindow|false|null
    {
        $exceptionToday = ShiftException::where('employee_id', $employee->id)
            ->where('date', $today)
            ->first();

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
            return null;
        }

        $windowToday = $this->buildExceptionWindow($exceptionToday, $today, $timezone);

        return $windowToday !== null && $windowToday->contains($instant)
            ? $windowToday
            : false;
    }

    private function resolveFromEmployeeShifts(User $employee, CarbonImmutable $instant, string $today, string $yesterday, string $timezone): ShiftWindow|false|null
    {
        $startOfYesterdayLocal = CarbonImmutable::parse($yesterday, $timezone)->utc();
        $shiftAtYesterdayAnchor = $this->shiftsActiveAt($employee, $startOfYesterdayLocal)->first();

        $windowYesterday = $shiftAtYesterdayAnchor !== null
            ? $this->buildTemplateWindow($shiftAtYesterdayAnchor->template, $yesterday, $timezone, ShiftWindowSource::EmployeeShift)
            : null;
        if ($windowYesterday !== null && $windowYesterday->contains($instant)) {
            return $windowYesterday;
        }

        $shiftAtInstant = $this->shiftsActiveAt($employee, $instant)->first();

        if ($shiftAtInstant === null) {
            return null;
        }

        $windowToday = $this->buildTemplateWindow($shiftAtInstant->template, $today, $timezone, ShiftWindowSource::EmployeeShift);

        return $windowToday !== null && $windowToday->contains($instant)
            ? $windowToday
            : false;
    }

    /**
     * @return Builder<EmployeeShift>
     */
    private function shiftsActiveAt(User $employee, CarbonImmutable $instant)
    {
        return $employee->employeeShifts()
            ->where('effective_from', '<=', $instant)
            ->where(function ($query) use ($instant) {
                $query->whereNull('effective_to')->orWhere('effective_to', '>', $instant);
            })
            ->orderByDesc('effective_from')
            ->with('template');
    }

    /**
     * Day-level views (`resolveAllForDate`, `resolveGoverningWindowForDate`) need every
     * assignment that was effective at any point during the day, not just at its
     * midnight instant — a shift assigned mid-day (`effective_from` cannot be in the
     * past, so any same-day assignment lands after midnight) is still relevant to that
     * day's history even though `shiftsActiveAt(midnight)` would miss it. Point-in-time
     * gating (`resolve()`, via `shiftsActiveAt`) is unaffected and still checks the
     * exact instant.
     *
     * @return Builder<EmployeeShift>
     */
    private function shiftsOverlappingDay(User $employee, CarbonImmutable $startOfDay, CarbonImmutable $endOfDay)
    {
        return $employee->employeeShifts()
            ->where('effective_from', '<', $endOfDay)
            ->where(function ($query) use ($startOfDay) {
                $query->whereNull('effective_to')->orWhere('effective_to', '>', $startOfDay);
            })
            ->orderByDesc('effective_from')
            ->with('template');
    }

    private function buildExceptionWindow(?ShiftException $exception, string $anchorDate, string $timezone): ?ShiftWindow
    {
        if ($exception === null) {
            return null;
        }

        if (! $exception->type->definesWindow() || $exception->start_at === null || $exception->end_at === null) {
            return null;
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
