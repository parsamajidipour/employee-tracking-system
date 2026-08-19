<?php

namespace Tests\Feature\Services;

use App\Enums\ShiftWindowSource;
use App\Models\EmployeeShift;
use App\Models\ShiftException;
use App\Models\ShiftTemplate;
use App\Models\User;
use App\Services\ShiftWindowResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftWindowResolverTest extends TestCase
{
    use RefreshDatabase;

    private ShiftWindowResolver $resolver;

    private CarbonImmutable $sunday;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new ShiftWindowResolver;
        $this->sunday = CarbonImmutable::parse('next Sunday', 'Asia/Muscat')->startOfDay();
    }

    private function assignShift(User $employee, ShiftTemplate $template, ?CarbonImmutable $effectiveFrom = null): EmployeeShift
    {
        return EmployeeShift::factory()->create([
            'employee_id' => $employee->id,
            'template_id' => $template->id,
            'effective_from' => ($effectiveFrom ?? $this->sunday->subMonth())->utc(),
            'effective_to' => null,
        ]);
    }

    public function test_instant_inside_an_assigned_shift_window_resolves(): void
    {
        $template = ShiftTemplate::factory()->create();
        $employee = User::factory()->create();
        $this->assignShift($employee, $template);

        $thursday = $this->sunday->addDays(4);
        $instant = $thursday->setTime(10, 0);

        $window = $this->resolver->resolve($employee, $instant);

        $this->assertNotNull($window);
        $this->assertSame(ShiftWindowSource::EmployeeShift, $window->source);
        $this->assertTrue($window->contains($instant->utc()));
    }

    public function test_instant_outside_an_assigned_shift_window_resolves_to_null(): void
    {
        $template = ShiftTemplate::factory()->create();
        $employee = User::factory()->create();
        $this->assignShift($employee, $template);

        $thursday = $this->sunday->addDays(4);
        $instant = $thursday->setTime(18, 0);

        $this->assertNull($this->resolver->resolve($employee, $instant));
    }

    public function test_employee_with_no_shift_at_all_resolves_to_null(): void
    {
        ShiftTemplate::factory()->create();
        $employee = User::factory()->create();

        $this->assertNull($this->resolver->resolve($employee, CarbonImmutable::now()));
    }

    public function test_a_shift_template_with_no_assignments_never_produces_a_window_for_other_employees(): void
    {
        $template = ShiftTemplate::factory()->create();
        $assigned = User::factory()->create();
        $unassigned = User::factory()->create();
        $this->assignShift($assigned, $template);

        $thursday = $this->sunday->addDays(4);
        $instant = $thursday->setTime(10, 0);

        $this->assertNotNull($this->resolver->resolve($assigned, $instant));
        $this->assertNull($this->resolver->resolve($unassigned, $instant));
    }

    public function test_employee_shift_before_its_effective_from_resolves_to_null(): void
    {
        $override = ShiftTemplate::factory()->create([
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ]);
        $employee = User::factory()->create();

        $thursday = $this->sunday->addDays(4);
        $this->assignShift($employee, $override, $thursday->addWeek());

        $instant = $thursday->setTime(10, 0);

        $this->assertNull($this->resolver->resolve($employee, $instant));
    }

    public function test_employee_shift_expired_yesterday_does_not_apply_today(): void
    {
        $override = ShiftTemplate::factory()->create([
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ]);
        $employee = User::factory()->create();

        $thursday = $this->sunday->addDays(4);
        EmployeeShift::factory()->create([
            'employee_id' => $employee->id,
            'template_id' => $override->id,
            'effective_from' => $thursday->subMonth()->utc(),
            'effective_to' => $thursday->subDay()->utc(),
        ]);

        $instant = $thursday->setTime(10, 0);

        $this->assertNull($this->resolver->resolve($employee, $instant));
    }

    public function test_leave_exception_vetoes_a_day_the_assigned_shift_says_is_working(): void
    {
        $template = ShiftTemplate::factory()->create();
        $employee = User::factory()->create();
        $this->assignShift($employee, $template);

        $thursday = $this->sunday->addDays(4);
        ShiftException::factory()->leave()->create([
            'employee_id' => $employee->id,
            'date' => $thursday->toDateString(),
        ]);

        $instant = $thursday->setTime(10, 0);

        $this->assertNull($this->resolver->resolve($employee, $instant));
    }

    public function test_overtime_exception_extends_the_window(): void
    {
        $employee = User::factory()->create();

        $thursday = $this->sunday->addDays(4);
        ShiftException::factory()->overtime('07:00:00', '20:00:00')->create([
            'employee_id' => $employee->id,
            'date' => $thursday->toDateString(),
        ]);

        $instant = $thursday->setTime(18, 0);

        $window = $this->resolver->resolve($employee, $instant);

        $this->assertNotNull($window);
        $this->assertSame(ShiftWindowSource::Exception, $window->source);
    }

    public function test_utc_instant_resolves_correctly_against_configured_timezone(): void
    {
        $template = ShiftTemplate::factory()->create();
        $employee = User::factory()->create();
        $this->assignShift($employee, $template);

        $thursday = $this->sunday->addDays(4);
        $instant = CarbonImmutable::parse($thursday->toDateString().' 03:00:00', 'UTC');

        $window = $this->resolver->resolve($employee, $instant);

        $this->assertNotNull($window);
        $this->assertTrue($window->contains($instant));
    }

    public function test_grace_before_and_after_at_both_edges(): void
    {
        $template = ShiftTemplate::factory()->create([
            'grace_before_min' => 10,
            'grace_after_min' => 15,
        ]);
        $employee = User::factory()->create();
        $this->assignShift($employee, $template);

        $thursday = $this->sunday->addDays(4);

        $this->assertNotNull($this->resolver->resolve($employee, $thursday->setTime(6, 50, 0)));
        $this->assertNull($this->resolver->resolve($employee, $thursday->setTime(6, 49, 59)));
        $this->assertNotNull($this->resolver->resolve($employee, $thursday->setTime(16, 14, 59)));
        $this->assertNull($this->resolver->resolve($employee, $thursday->setTime(16, 15, 0)));
    }

    public function test_friday_resolves_to_null_under_a_sunday_thursday_shift(): void
    {
        $template = ShiftTemplate::factory()->create();
        $employee = User::factory()->create();
        $this->assignShift($employee, $template);

        $friday = $this->sunday->addDays(5);
        $instant = $friday->setTime(10, 0);

        $this->assertNull($this->resolver->resolve($employee, $instant));
    }

    public function test_night_shift_crossing_midnight_resolves_on_both_sides(): void
    {
        $template = ShiftTemplate::factory()->create([
            'start_time' => '22:00:00',
            'end_time' => '06:00:00',
            'days_of_week' => [0, 1, 2, 3, 4],
        ]);
        $employee = User::factory()->create();
        $this->assignShift($employee, $template);

        $wednesday = $this->sunday->addDays(3);
        $thursday = $this->sunday->addDays(4);

        $lateWednesdayNight = $wednesday->setTime(23, 0);
        $earlyThursdayMorning = $thursday->setTime(3, 0);

        $windowLate = $this->resolver->resolve($employee, $lateWednesdayNight);
        $windowEarly = $this->resolver->resolve($employee, $earlyThursdayMorning);

        $this->assertNotNull($windowLate);
        $this->assertNotNull($windowEarly);
        $this->assertTrue($windowLate->start->equalTo($windowEarly->start));
        $this->assertTrue($windowLate->end->equalTo($windowEarly->end));
    }

    public function test_leave_exception_vetoes_a_night_shift_bleeding_in_from_the_day_before(): void
    {
        $template = ShiftTemplate::factory()->create([
            'start_time' => '22:00:00',
            'end_time' => '06:00:00',
            'days_of_week' => [0, 1, 2, 3, 4],
        ]);
        $employee = User::factory()->create();
        $this->assignShift($employee, $template);

        $thursday = $this->sunday->addDays(4);
        ShiftException::factory()->leave()->create([
            'employee_id' => $employee->id,
            'date' => $thursday->toDateString(),
        ]);

        $instant = $thursday->setTime(2, 0);

        $this->assertNull($this->resolver->resolve($employee, $instant));
    }

    public function test_effective_from_at_midday_splits_old_and_new_window_same_day(): void
    {
        $oldTemplate = ShiftTemplate::factory()->create([
            'start_time' => '07:00:00',
            'end_time' => '16:00:00',
        ]);
        $newTemplate = ShiftTemplate::factory()->create([
            'start_time' => '09:00:00',
            'end_time' => '18:00:00',
        ]);
        $employee = User::factory()->create();

        $thursday = $this->sunday->addDays(4);
        $cutover = $thursday->setTime(12, 0);

        EmployeeShift::factory()->create([
            'employee_id' => $employee->id,
            'template_id' => $oldTemplate->id,
            'effective_from' => $thursday->subMonth()->utc(),
            'effective_to' => $cutover->utc(),
        ]);
        EmployeeShift::factory()->create([
            'employee_id' => $employee->id,
            'template_id' => $newTemplate->id,
            'effective_from' => $cutover->utc(),
            'effective_to' => null,
        ]);

        $before = $cutover->subMinute();
        $after = $cutover;

        $windowBefore = $this->resolver->resolve($employee, $before);
        $windowAfter = $this->resolver->resolve($employee, $after);

        $this->assertNotNull($windowBefore);
        $this->assertNotNull($windowAfter);
        $this->assertSame('07', $windowBefore->start->setTimezone('Asia/Muscat')->format('H'));
        $this->assertSame('09', $windowAfter->start->setTimezone('Asia/Muscat')->format('H'));
    }

    public function test_resolve_all_for_date_includes_a_shift_assigned_later_the_same_day(): void
    {
        $template = ShiftTemplate::factory()->create([
            'start_time' => '07:00:00',
            'end_time' => '16:00:00',
            'days_of_week' => [0, 1, 2, 3, 4],
        ]);
        $employee = User::factory()->create();

        $assignedAt = $this->sunday->setTime(10, 0);

        EmployeeShift::factory()->create([
            'employee_id' => $employee->id,
            'template_id' => $template->id,
            'effective_from' => $assignedAt->utc(),
            'effective_to' => null,
        ]);

        $windows = $this->resolver->resolveAllForDate($employee, $this->sunday->toDateString());

        $this->assertCount(1, $windows);
        $this->assertSame('07', $windows->first()->start->setTimezone('Asia/Muscat')->format('H'));
    }

    public function test_resolve_for_date_includes_a_shift_assigned_later_the_same_day(): void
    {
        $template = ShiftTemplate::factory()->create([
            'start_time' => '07:00:00',
            'end_time' => '16:00:00',
            'days_of_week' => [0, 1, 2, 3, 4],
        ]);
        $employee = User::factory()->create();

        $assignedAt = $this->sunday->setTime(10, 0);

        EmployeeShift::factory()->create([
            'employee_id' => $employee->id,
            'template_id' => $template->id,
            'effective_from' => $assignedAt->utc(),
            'effective_to' => null,
        ]);

        $window = $this->resolver->resolveForDate($employee, $this->sunday->toDateString());

        $this->assertNotNull($window);
    }

    public function test_employee_shift_silent_on_a_day_resolves_to_null(): void
    {
        $partTime = ShiftTemplate::factory()->create([
            'days_of_week' => [0, 2, 4],
        ]);
        $employee = User::factory()->create();
        $this->assignShift($employee, $partTime, $this->sunday->subYear());

        $monday = $this->sunday->addDays(1);
        $instant = $monday->setTime(10, 0);

        $this->assertNull($this->resolver->resolve($employee, $instant));
    }

    public function test_resolve_next_returns_todays_later_window_when_before_todays_start(): void
    {
        $template = ShiftTemplate::factory()->create();
        $employee = User::factory()->create();
        $this->assignShift($employee, $template);

        $thursday = $this->sunday->addDays(4);
        $after = $thursday->setTime(5, 0);

        $next = $this->resolver->resolveNext($employee, $after);

        $this->assertNotNull($next);
        $this->assertTrue($next->effectiveStart()->equalTo($thursday->setTime(7, 0)->utc()));
    }

    public function test_resolve_next_skips_the_weekend_when_currently_inside_thursdays_window(): void
    {
        $template = ShiftTemplate::factory()->create();
        $employee = User::factory()->create();
        $this->assignShift($employee, $template);

        $thursday = $this->sunday->addDays(4);
        $after = $thursday->setTime(10, 0);

        $next = $this->resolver->resolveNext($employee, $after);

        $nextSunday = $this->sunday->addWeek();
        $this->assertNotNull($next);
        $this->assertTrue($next->effectiveStart()->equalTo($nextSunday->setTime(7, 0)->utc()));
    }

    public function test_resolve_next_skips_a_leave_day_and_lands_on_the_following_working_day(): void
    {
        $template = ShiftTemplate::factory()->create();
        $employee = User::factory()->create();
        $this->assignShift($employee, $template);

        $thursday = $this->sunday->addDays(4);
        ShiftException::factory()->leave()->create([
            'employee_id' => $employee->id,
            'date' => $thursday->toDateString(),
        ]);

        $after = $thursday->setTime(3, 0);

        $next = $this->resolver->resolveNext($employee, $after);

        $nextSunday = $this->sunday->addWeek();
        $this->assertNotNull($next);
        $this->assertTrue($next->effectiveStart()->equalTo($nextSunday->setTime(7, 0)->utc()));
    }

    public function test_resolve_next_returns_null_when_no_shift_is_assigned(): void
    {
        ShiftTemplate::factory()->create();
        $employee = User::factory()->create();

        $this->assertNull($this->resolver->resolveNext($employee, CarbonImmutable::now()));
    }
}
