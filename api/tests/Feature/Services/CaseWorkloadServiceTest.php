<?php

namespace Tests\Feature\Services;

use App\Models\CasePhoto;
use App\Models\ShiftTemplate;
use App\Models\User;
use App\Services\CaseLifecycleService;
use App\Services\CaseWorkloadService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        CasePhoto::create([
            'inspection_case_id' => $completed->id,
            'employee_id' => $employee->id,
            'disk_path' => 'case-photos/test.png',
            'location' => DB::raw('ST_SetSRID(ST_MakePoint(58.35, 23.55), 4326)::geography'),
            'accuracy_m' => 5,
            'distance_from_case_m' => 0,
            'is_gps_verified' => true,
            'captured_at' => now(),
        ]);
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

    public function test_summary_separates_scheduled_overdue_and_in_progress_cases(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-26 10:00:00'));
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->create();
        $lifecycle = app(CaseLifecycleService::class);
        $attributes = ['property_address' => null, 'lat' => 23.55, 'lng' => 58.35, 'priority' => 'normal'];

        $scheduled = $lifecycle->create($attributes + ['reference_no' => 'W-SCHEDULED', 'title' => 'Scheduled'], $admin);
        $scheduled = $lifecycle->assign($scheduled, $employee, $admin);
        $lifecycle->accept($scheduled, $employee, CarbonImmutable::now()->addHour());

        $late = $lifecycle->create($attributes + ['reference_no' => 'W-LATE', 'title' => 'Late'], $admin);
        $late = $lifecycle->assign($late, $employee, $admin);
        $lifecycle->accept($late, $employee, CarbonImmutable::now()->subHour());

        $started = $lifecycle->create($attributes + ['reference_no' => 'W-STARTED', 'title' => 'Started'], $admin);
        $started = $lifecycle->assign($started, $employee, $admin);
        $started = $lifecycle->accept($started, $employee, CarbonImmutable::now()->subHour());
        $lifecycle->start($started, $employee);

        $overdue = $lifecycle->create($attributes + ['reference_no' => 'W-OVERDUE', 'title' => 'Overdue'], $admin);
        $overdue = $lifecycle->assign($overdue, $employee, $admin);
        $overdue = $lifecycle->accept($overdue, $employee, CarbonImmutable::now()->subHour());
        $lifecycle->markOverdue($overdue);

        $summary = app(CaseWorkloadService::class)->summary($employee->fresh());

        $this->assertSame(4, $summary['active_cases']);
        $this->assertSame(1, $summary['scheduled']);
        $this->assertSame(1, $summary['in_progress']);
        $this->assertSame(2, $summary['overdue']);
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
