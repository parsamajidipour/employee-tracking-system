<?php

namespace Tests\Feature\Services;

use App\Models\ShiftTemplate;
use App\Models\User;
use App\Services\CaseLifecycleService;
use App\Services\CaseWorkloadService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseWorkloadServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_summary_counts_open_pending_and_completed_cases(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->create();
        $lifecycle = app(CaseLifecycleService::class);

        $pending = $lifecycle->create(['reference_no' => 'W-1', 'title' => 'A', 'property_address' => null, 'lat' => 23.55, 'lng' => 58.35, 'priority' => 'normal'], $admin);
        $lifecycle->assign($pending, $employee, $admin);

        $completed = $lifecycle->create(['reference_no' => 'W-2', 'title' => 'B', 'property_address' => null, 'lat' => 23.55, 'lng' => 58.35, 'priority' => 'normal'], $admin);
        $completed = $lifecycle->assign($completed, $employee, $admin);
        $completed = $lifecycle->accept($completed, $employee, CarbonImmutable::now());
        $completed = $lifecycle->start($completed, $employee);
        $lifecycle->complete($completed, $employee, null);

        $summary = app(CaseWorkloadService::class)->summary($employee->fresh());

        $this->assertSame(1, $summary['active_cases']);
        $this->assertSame(1, $summary['pending']);
        $this->assertSame(1, $summary['completed_today']);
    }

    public function test_daily_activity_returns_zeros_when_no_shift_window_resolves(): void
    {
        $employee = User::factory()->create();

        $activity = app(CaseWorkloadService::class)->dailyActivity($employee, CarbonImmutable::now()->toDateString());

        $this->assertNull($activity['window_minutes']);
        $this->assertSame(0.0, $activity['distance_m']);
    }

    public function test_daily_activity_attributes_time_to_an_in_progress_case(): void
    {
        $template = ShiftTemplate::factory()->create(['start_time' => '08:00', 'end_time' => '17:00']);
        $sunday = CarbonImmutable::parse('next Sunday', 'Asia/Muscat')->startOfDay();
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->create();
        $employee->employeeShifts()->create(['template_id' => $template->id, 'effective_from' => $sunday->subMonth()]);

        $lifecycle = app(CaseLifecycleService::class);

        CarbonImmutable::setTestNow($sunday->setTime(9, 0));
        $case = $lifecycle->create(['reference_no' => 'W-3', 'title' => 'C', 'property_address' => null, 'lat' => 23.55, 'lng' => 58.35, 'priority' => 'normal'], $admin);
        $case = $lifecycle->assign($case, $employee, $admin);
        $case = $lifecycle->accept($case, $employee, CarbonImmutable::now());
        $lifecycle->start($case, $employee);

        CarbonImmutable::setTestNow($sunday->setTime(9, 30));

        $activity = app(CaseWorkloadService::class)->dailyActivity($employee, $sunday->toDateString());

        $this->assertEqualsWithDelta(30.0, $activity['inspection_minutes'], 1.0);
    }
}
