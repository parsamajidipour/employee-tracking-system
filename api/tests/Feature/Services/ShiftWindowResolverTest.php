<?php

namespace Tests\Feature\Services;

use App\Enums\ShiftWindowSource;
use App\Models\EmployeeShift;
use App\Models\ShiftException;
use App\Models\ShiftTemplate;
use App\Models\Team;
use App\Models\User;
use App\Services\ShiftWindowResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftWindowResolverTest extends TestCase
{
    use RefreshDatabase;

    private ShiftWindowResolver $resolver;

    /**
     * A stable Sunday, always in the future relative to whenever the test
     * runs, used as the base for every other weekday in these tests via
     * fixed day-offsets (never re-derived per weekday name) — this is what
     * keeps "Wednesday", "Thursday", "Friday" etc. genuinely consecutive
     * regardless of what day it actually is when the suite runs.
     */
    private CarbonImmutable $sunday;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new ShiftWindowResolver();
        $this->sunday = CarbonImmutable::parse('next Sunday', 'Asia/Muscat')->startOfDay();
    }

    private function team(): Team
    {
        return Team::factory()->create(['timezone' => 'Asia/Muscat']);
    }

    public function test_instant_inside_a_plain_template_window_resolves(): void
    {
        $team = $this->team();
        ShiftTemplate::factory()->create(['team_id' => $team->id]); // Sun-Thu 07:00-16:00
        $employee = User::factory()->create(['team_id' => $team->id]);

        $thursday = $this->sunday->addDays(4);
        $instant = $thursday->setTime(10, 0);

        $window = $this->resolver->resolve($employee, $instant);

        $this->assertNotNull($window);
        $this->assertSame(ShiftWindowSource::TeamTemplate, $window->source);
        $this->assertTrue($window->contains($instant->utc()));
    }

    public function test_instant_outside_a_plain_template_window_resolves_to_null(): void
    {
        $team = $this->team();
        ShiftTemplate::factory()->create(['team_id' => $team->id]); // 07:00-16:00
        $employee = User::factory()->create(['team_id' => $team->id]);

        $thursday = $this->sunday->addDays(4);
        $instant = $thursday->setTime(18, 0);

        $this->assertNull($this->resolver->resolve($employee, $instant));
    }

    public function test_employee_with_no_team_or_no_template_at_all_resolves_to_null(): void
    {
        $noTeam = User::factory()->create(['team_id' => null]);
        $this->assertNull($this->resolver->resolve($noTeam, CarbonImmutable::now()));

        $teamWithNoTemplates = $this->team();
        $employeeOnEmptyTeam = User::factory()->create(['team_id' => $teamWithNoTemplates->id]);
        $this->assertNull($this->resolver->resolve($employeeOnEmptyTeam, CarbonImmutable::now()));
    }

    public function test_employee_shift_overrides_the_team_template(): void
    {
        $team = $this->team();
        ShiftTemplate::factory()->create(['team_id' => $team->id]); // team default 07:00-16:00
        $override = ShiftTemplate::factory()->create([
            'team_id' => $team->id,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ]);
        $employee = User::factory()->create(['team_id' => $team->id]);

        $thursday = $this->sunday->addDays(4);
        EmployeeShift::factory()->create([
            'employee_id' => $employee->id,
            'template_id' => $override->id,
            'effective_from' => $thursday->subMonth()->utc(),
            'effective_to' => null,
        ]);

        // 16:30 is outside the team template (ends 16:00) but inside the
        // employee's own override (ends 17:00) — proves the override, not
        // the team template, governs.
        $instant = $thursday->setTime(16, 30);

        $window = $this->resolver->resolve($employee, $instant);

        $this->assertNotNull($window);
        $this->assertSame(ShiftWindowSource::EmployeeShift, $window->source);
    }

    public function test_employee_shift_before_its_effective_from_falls_back_to_team_template(): void
    {
        $team = $this->team();
        ShiftTemplate::factory()->create(['team_id' => $team->id]); // team default 07:00-16:00
        $override = ShiftTemplate::factory()->create([
            'team_id' => $team->id,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ]);
        $employee = User::factory()->create(['team_id' => $team->id]);

        $thursday = $this->sunday->addDays(4);
        EmployeeShift::factory()->create([
            'employee_id' => $employee->id,
            'template_id' => $override->id,
            'effective_from' => $thursday->addWeek()->utc(), // starts next week — not yet active
            'effective_to' => null,
        ]);

        $instant = $thursday->setTime(10, 0);

        $window = $this->resolver->resolve($employee, $instant);

        $this->assertNotNull($window);
        $this->assertSame(ShiftWindowSource::TeamTemplate, $window->source);
    }

    public function test_employee_shift_expired_yesterday_does_not_apply_today(): void
    {
        $team = $this->team();
        ShiftTemplate::factory()->create(['team_id' => $team->id]); // team default 07:00-16:00
        $override = ShiftTemplate::factory()->create([
            'team_id' => $team->id,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ]);
        $employee = User::factory()->create(['team_id' => $team->id]);

        $thursday = $this->sunday->addDays(4);
        EmployeeShift::factory()->create([
            'employee_id' => $employee->id,
            'template_id' => $override->id,
            'effective_from' => $thursday->subMonth()->utc(),
            'effective_to' => $thursday->subDay()->utc(), // expired yesterday
        ]);

        $instant = $thursday->setTime(10, 0);

        $window = $this->resolver->resolve($employee, $instant);

        $this->assertNotNull($window);
        $this->assertSame(ShiftWindowSource::TeamTemplate, $window->source);
    }

    public function test_leave_exception_produces_null_on_a_day_the_template_says_is_working(): void
    {
        $team = $this->team();
        ShiftTemplate::factory()->create(['team_id' => $team->id]); // Thursday is a working day
        $employee = User::factory()->create(['team_id' => $team->id]);

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
        $team = $this->team();
        ShiftTemplate::factory()->create(['team_id' => $team->id]); // 07:00-16:00
        $employee = User::factory()->create(['team_id' => $team->id]);

        $thursday = $this->sunday->addDays(4);
        ShiftException::factory()->overtime('07:00:00', '20:00:00')->create([
            'employee_id' => $employee->id,
            'date' => $thursday->toDateString(),
        ]);

        // 18:00 is past the template's 16:00 end but inside the overtime
        // exception's 20:00 end.
        $instant = $thursday->setTime(18, 0);

        $window = $this->resolver->resolve($employee, $instant);

        $this->assertNotNull($window);
        $this->assertSame(ShiftWindowSource::Exception, $window->source);
    }

    public function test_utc_instant_resolves_correctly_against_team_timezone(): void
    {
        $team = $this->team();
        ShiftTemplate::factory()->create(['team_id' => $team->id]); // 07:00-16:00 Asia/Muscat (+4)
        $employee = User::factory()->create(['team_id' => $team->id]);

        $thursday = $this->sunday->addDays(4);
        // 03:00 UTC == 07:00 local (+4) — exactly the window's start.
        $instant = CarbonImmutable::parse($thursday->toDateString().' 03:00:00', 'UTC');

        $window = $this->resolver->resolve($employee, $instant);

        $this->assertNotNull($window);
        $this->assertTrue($window->contains($instant));
    }

    public function test_grace_before_and_after_at_both_edges(): void
    {
        $team = $this->team();
        ShiftTemplate::factory()->create([
            'team_id' => $team->id,
            'grace_before_min' => 10,
            'grace_after_min' => 15,
        ]);
        $employee = User::factory()->create(['team_id' => $team->id]);

        $thursday = $this->sunday->addDays(4);

        // Core window 07:00-16:00. Effective (graced) window 06:50-16:15.
        $this->assertNotNull($this->resolver->resolve($employee, $thursday->setTime(6, 50, 0)));
        $this->assertNull($this->resolver->resolve($employee, $thursday->setTime(6, 49, 59)));
        $this->assertNotNull($this->resolver->resolve($employee, $thursday->setTime(16, 14, 59)));
        $this->assertNull($this->resolver->resolve($employee, $thursday->setTime(16, 15, 0)));
    }

    public function test_friday_resolves_to_null_under_a_sunday_thursday_template(): void
    {
        $team = $this->team();
        ShiftTemplate::factory()->create(['team_id' => $team->id]); // Sun-Thu
        $employee = User::factory()->create(['team_id' => $team->id]);

        $friday = $this->sunday->addDays(5);
        $instant = $friday->setTime(10, 0);

        $this->assertNull($this->resolver->resolve($employee, $instant));
    }

    public function test_night_shift_crossing_midnight_resolves_on_both_sides(): void
    {
        $team = $this->team();
        ShiftTemplate::factory()->create([
            'team_id' => $team->id,
            'start_time' => '22:00:00',
            'end_time' => '06:00:00',
            'days_of_week' => [0, 1, 2, 3, 4], // shift starts Sun-Thu
        ]);
        $employee = User::factory()->create(['team_id' => $team->id]);

        $wednesday = $this->sunday->addDays(3);
        $thursday = $this->sunday->addDays(4);

        $lateWednesdayNight = $wednesday->setTime(23, 0);
        $earlyThursdayMorning = $thursday->setTime(3, 0);

        $windowLate = $this->resolver->resolve($employee, $lateWednesdayNight);
        $windowEarly = $this->resolver->resolve($employee, $earlyThursdayMorning);

        $this->assertNotNull($windowLate);
        $this->assertNotNull($windowEarly);
        // Same shift, anchored on Wednesday's start — one continuous window.
        $this->assertTrue($windowLate->start->equalTo($windowEarly->start));
        $this->assertTrue($windowLate->end->equalTo($windowEarly->end));
    }

    public function test_leave_exception_vetoes_a_night_shift_bleeding_in_from_the_day_before(): void
    {
        $team = $this->team();
        ShiftTemplate::factory()->create([
            'team_id' => $team->id,
            'start_time' => '22:00:00',
            'end_time' => '06:00:00',
            'days_of_week' => [0, 1, 2, 3, 4], // shift starts Sun-Thu
        ]);
        $employee = User::factory()->create(['team_id' => $team->id]);

        $thursday = $this->sunday->addDays(4);
        // The night shift starting Wednesday 22:00 would otherwise run into
        // Thursday 06:00. A leave exception dated Thursday must veto that,
        // even though the shift is anchored on the day before.
        ShiftException::factory()->leave()->create([
            'employee_id' => $employee->id,
            'date' => $thursday->toDateString(),
        ]);

        $instant = $thursday->setTime(2, 0);

        $this->assertNull($this->resolver->resolve($employee, $instant));
    }

    public function test_effective_from_at_midday_splits_old_and_new_window_same_day(): void
    {
        $team = $this->team();
        $oldTemplate = ShiftTemplate::factory()->create([
            'team_id' => $team->id,
            'start_time' => '07:00:00',
            'end_time' => '16:00:00',
        ]);
        $newTemplate = ShiftTemplate::factory()->create([
            'team_id' => $team->id,
            'start_time' => '09:00:00',
            'end_time' => '18:00:00',
        ]);
        $employee = User::factory()->create(['team_id' => $team->id]);

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

    public function test_employee_shift_silent_on_a_day_does_not_fall_back_to_team_template(): void
    {
        $team = $this->team();
        ShiftTemplate::factory()->create(['team_id' => $team->id]); // team default includes Monday
        $partTime = ShiftTemplate::factory()->create([
            'team_id' => $team->id,
            'days_of_week' => [0, 2, 4], // Sun, Tue, Thu only — silent on Monday
        ]);
        $employee = User::factory()->create(['team_id' => $team->id]);

        EmployeeShift::factory()->create([
            'employee_id' => $employee->id,
            'template_id' => $partTime->id,
            'effective_from' => $this->sunday->subYear()->utc(),
            'effective_to' => null,
        ]);

        $monday = $this->sunday->addDays(1);
        $instant = $monday->setTime(10, 0);

        // The override is silent about Monday — it must NOT fall back to
        // the team template just because that template works Mondays.
        $this->assertNull($this->resolver->resolve($employee, $instant));
    }

    public function test_resolve_next_returns_todays_later_window_when_before_todays_start(): void
    {
        $team = $this->team();
        ShiftTemplate::factory()->create(['team_id' => $team->id]); // Sun-Thu 07:00-16:00
        $employee = User::factory()->create(['team_id' => $team->id]);

        $thursday = $this->sunday->addDays(4);
        $after = $thursday->setTime(5, 0);

        $next = $this->resolver->resolveNext($employee, $after);

        $this->assertNotNull($next);
        $this->assertTrue($next->effectiveStart()->equalTo($thursday->setTime(7, 0)->utc()));
    }

    public function test_resolve_next_skips_the_weekend_when_currently_inside_thursdays_window(): void
    {
        $team = $this->team();
        ShiftTemplate::factory()->create(['team_id' => $team->id]); // Sun-Thu 07:00-16:00
        $employee = User::factory()->create(['team_id' => $team->id]);

        $thursday = $this->sunday->addDays(4);
        $after = $thursday->setTime(10, 0); // currently inside Thursday's window

        $next = $this->resolver->resolveNext($employee, $after);

        $nextSunday = $this->sunday->addWeek();
        $this->assertNotNull($next);
        $this->assertTrue($next->effectiveStart()->equalTo($nextSunday->setTime(7, 0)->utc()));
    }

    public function test_resolve_next_skips_a_leave_day_and_lands_on_the_following_working_day(): void
    {
        $team = $this->team();
        ShiftTemplate::factory()->create(['team_id' => $team->id]); // Sun-Thu 07:00-16:00
        $employee = User::factory()->create(['team_id' => $team->id]);

        $thursday = $this->sunday->addDays(4);
        ShiftException::factory()->leave()->create([
            'employee_id' => $employee->id,
            'date' => $thursday->toDateString(),
        ]);

        $after = $thursday->setTime(3, 0); // before Thursday's own window, but Thursday is leave

        $next = $this->resolver->resolveNext($employee, $after);

        $nextSunday = $this->sunday->addWeek();
        $this->assertNotNull($next);
        $this->assertTrue($next->effectiveStart()->equalTo($nextSunday->setTime(7, 0)->utc()));
    }

    public function test_resolve_next_returns_null_for_an_employee_with_no_team(): void
    {
        $noTeam = User::factory()->create(['team_id' => null]);

        $this->assertNull($this->resolver->resolveNext($noTeam, CarbonImmutable::now()));
    }
}
