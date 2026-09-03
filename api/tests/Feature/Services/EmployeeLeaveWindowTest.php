<?php

namespace Tests\Feature\Services;

use App\Models\EmployeeLeave;
use App\Models\EmployeeShift;
use App\Models\ShiftTemplate;
use App\Models\User;
use App\Services\ShiftWindowResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeLeaveWindowTest extends TestCase
{
    use RefreshDatabase;

    private ShiftWindowResolver $resolver;

    private CarbonImmutable $sunday;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new ShiftWindowResolver;
        $this->sunday = CarbonImmutable::parse('next Sunday', 'Asia/Muscat')->startOfDay();

        $template = ShiftTemplate::factory()->create([
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);
        $this->employee = User::factory()->create();

        EmployeeShift::factory()->create([
            'employee_id' => $this->employee->id,
            'template_id' => $template->id,
            'effective_from' => $this->sunday->subMonth()->utc(),
            'effective_to' => null,
        ]);
    }

    private function leave(CarbonImmutable $from, CarbonImmutable $to): EmployeeLeave
    {
        return EmployeeLeave::create([
            'employee_id' => $this->employee->id,
            'starts_at' => $from->utc(),
            'ends_at' => $to->utc(),
            'note' => 'Leave',
        ]);
    }

    public function test_an_instant_inside_a_leave_resolves_to_null(): void
    {
        $this->leave($this->sunday->setTime(0, 0), $this->sunday->addDay()->setTime(0, 0));

        $this->assertNull($this->resolver->resolve($this->employee, $this->sunday->setTime(10, 0)));
    }

    public function test_a_leave_does_not_affect_a_day_it_does_not_cover(): void
    {
        $this->leave($this->sunday->setTime(0, 0), $this->sunday->addDay()->setTime(0, 0));

        $this->assertNotNull($this->resolver->resolve($this->employee, $this->sunday->addDay()->setTime(10, 0)));
    }

    public function test_a_multi_day_leave_denies_every_instant_in_its_continuous_range(): void
    {
        $this->leave($this->sunday->setTime(12, 0), $this->sunday->addDays(2)->setTime(12, 0));

        $this->assertNotNull($this->resolver->resolve($this->employee, $this->sunday->setTime(9, 0)));
        $this->assertNull($this->resolver->resolve($this->employee, $this->sunday->setTime(13, 0)));
        $this->assertNull($this->resolver->resolve($this->employee, $this->sunday->addDay()->setTime(10, 0)));
        $this->assertNull($this->resolver->resolve($this->employee, $this->sunday->addDays(2)->setTime(9, 0)));
        $this->assertNotNull($this->resolver->resolve($this->employee, $this->sunday->addDays(2)->setTime(14, 0)));
    }

    public function test_a_leave_covering_the_start_of_a_day_clips_the_window_start(): void
    {
        $this->leave($this->sunday->setTime(0, 0), $this->sunday->setTime(12, 0));

        $window = $this->resolver->resolveForDate($this->employee, $this->sunday->toDateString());

        $this->assertNotNull($window);
        $this->assertSame($this->sunday->setTime(12, 0)->utc()->toISOString(), $window->effectiveStart()->toISOString());
        $this->assertSame($this->sunday->setTime(17, 0)->utc()->toISOString(), $window->effectiveEnd()->toISOString());
    }

    public function test_a_leave_covering_the_end_of_a_day_clips_the_window_end(): void
    {
        $this->leave($this->sunday->setTime(12, 0), $this->sunday->addDay()->setTime(0, 0));

        $window = $this->resolver->resolveForDate($this->employee, $this->sunday->toDateString());

        $this->assertNotNull($window);
        $this->assertSame($this->sunday->setTime(8, 0)->utc()->toISOString(), $window->effectiveStart()->toISOString());
        $this->assertSame($this->sunday->setTime(12, 0)->utc()->toISOString(), $window->effectiveEnd()->toISOString());
    }

    public function test_a_leave_covering_a_whole_day_removes_that_days_window(): void
    {
        $this->leave($this->sunday->setTime(0, 0), $this->sunday->addDay()->setTime(0, 0));

        $this->assertNull($this->resolver->resolveForDate($this->employee, $this->sunday->toDateString()));
        $this->assertCount(0, $this->resolver->resolveAllForDate($this->employee, $this->sunday->toDateString()));
    }

    public function test_the_next_window_skips_a_day_fully_covered_by_a_leave(): void
    {
        $this->leave($this->sunday->setTime(0, 0), $this->sunday->addDay()->setTime(0, 0));

        $next = $this->resolver->resolveNext($this->employee, $this->sunday->subDay()->setTime(20, 0));

        $this->assertNotNull($next);
        $this->assertSame($this->sunday->addDay()->setTime(8, 0)->utc()->toISOString(), $next->effectiveStart()->toISOString());
    }

    public function test_cancelling_a_leave_restores_the_window(): void
    {
        $leave = $this->leave($this->sunday->setTime(0, 0), $this->sunday->addDay()->setTime(0, 0));
        $this->assertNull($this->resolver->resolve($this->employee, $this->sunday->setTime(10, 0)));

        $leave->delete();

        $this->assertNotNull($this->resolver->resolve($this->employee, $this->sunday->setTime(10, 0)));
        $this->assertSoftDeleted('employee_leaves', ['id' => $leave->id]);
    }
}
