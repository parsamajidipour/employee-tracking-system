<?php

namespace App\Services;

use App\Enums\ShiftWindowSource;
use App\Models\ShiftException;
use App\Models\ShiftTemplate;
use App\Models\User;
use App\ValueObjects\ShiftWindow;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class ShiftWindowResolver
{
    private const NEXT_WINDOW_LOOKAHEAD_DAYS = 8;

    /**
     * @var Collection<int, ShiftTemplate>|null
     */
    private ?Collection $defaultTemplates = null;

    public function resolve(User $employee, CarbonInterface $instant): ?ShiftWindow
    {
        $instant = CarbonImmutable::instance($instant)->utc();

        $timezone = $this->timezone();
        $localInstant = $instant->setTimezone($timezone);
        $today = $localInstant->toDateString();
        $yesterday = $localInstant->subDay()->toDateString();

        $level = $this->resolveFromExceptions($employee, $instant, $today, $yesterday, $timezone);
        if ($level !== null) {
            return $level === false ? null : $level;
        }

        $level = $this->resolveFromEmployeeShifts($employee, $instant, $today, $yesterday, $timezone);
        if ($level !== null) {
            return $level === false ? null : $level;
        }

        return $this->resolveFromDefaultTemplates($instant, $today, $yesterday, $timezone);
    }

    public function resolveForDate(User $employee, string $localDate): ?ShiftWindow
    {
        return $this->resolveGoverningWindowForDate($employee, $localDate, $this->timezone());
    }

    public function resolveAllForDate(User $employee, string $localDate): Collection
    {
        $timezone = $this->timezone();
        $exception = ShiftException::where('employee_id', $employee->id)->where('date', $localDate)->first();

        if ($exception !== null) {
            $window = $exception->type->isDeny() ? null : $this->buildExceptionWindow($exception, $localDate, $timezone);

            return $window === null ? collect() : collect([$window]);
        }

        return $employee->employeeShifts()->with('template')->orderBy('template_id')->get()
            ->map(fn ($shift) => $this->buildTemplateWindow($shift->template, $localDate, $timezone, ShiftWindowSource::EmployeeShift))
            ->filter()
            ->sortBy(fn (ShiftWindow $window) => $window->effectiveStart()->getTimestamp())
            ->values();
    }

    public function resolveNext(User $employee, CarbonInterface $after): ?ShiftWindow
    {
        $after = CarbonImmutable::instance($after)->utc();

        $timezone = $this->timezone();
        $localAfter = $after->setTimezone($timezone);

        for ($offset = 0; $offset <= self::NEXT_WINDOW_LOOKAHEAD_DAYS; $offset++) {
            $anchorDate = $localAfter->addDays($offset)->toDateString();
            $window = $this->resolveGoverningWindowForDate($employee, $anchorDate, $timezone);

            if ($window !== null && $window->effectiveStart()->greaterThan($after)) {
                return $window;
            }
        }

        return null;
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

        $shift = $employee->employeeShifts()->with('template')->orderBy('template_id')->get()
            ->first(fn ($shift) => in_array(Carbon::parse($anchorDate)->dayOfWeek, $shift->template->days_of_week, true));

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
        $shifts = $employee->employeeShifts()->with('template')->orderBy('template_id')->get();

        if ($shifts->isEmpty()) {
            return false;
        }

        foreach ([$yesterday, $today] as $date) {
            foreach ($shifts as $shift) {
                $window = $this->buildTemplateWindow($shift->template, $date, $timezone, ShiftWindowSource::EmployeeShift);
                if ($window !== null && $window->contains($instant)) {
                    return $window;
                }
            }
        }

        return false;
    }

    private function resolveFromDefaultTemplates(CarbonImmutable $instant, string $today, string $yesterday, string $timezone): ?ShiftWindow
    {
        $windowYesterday = $this->buildTemplateWindow($this->pickTemplateFor($yesterday), $yesterday, $timezone, ShiftWindowSource::DefaultTemplate);
        if ($windowYesterday !== null && $windowYesterday->contains($instant)) {
            return $windowYesterday;
        }

        $windowToday = $this->buildTemplateWindow($this->pickTemplateFor($today), $today, $timezone, ShiftWindowSource::DefaultTemplate);
        if ($windowToday !== null && $windowToday->contains($instant)) {
            return $windowToday;
        }

        return null;
    }

    private function pickTemplateFor(string $anchorDate): ?ShiftTemplate
    {
        $dayOfWeek = Carbon::parse($anchorDate)->dayOfWeek;

        return $this->defaultTemplates()
            ->filter(fn (ShiftTemplate $template) => in_array($dayOfWeek, $template->days_of_week, true))
            ->sortBy('id')
            ->first();
    }

    /**
     * @return Collection<int, ShiftTemplate>
     */
    private function defaultTemplates(): Collection
    {
        return $this->defaultTemplates ??= ShiftTemplate::orderBy('id')->get();
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
