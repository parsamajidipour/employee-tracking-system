<?php

namespace Tests\Feature\Api\V1;

use App\Models\EmployeeLeave;
use App\Models\ScheduleChangeLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeLeaveControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->hr()->create();
        $this->employee = User::factory()->create();

        $this->actingAs($this->admin);
    }

    private function leave(CarbonImmutable $from, CarbonImmutable $to): EmployeeLeave
    {
        return EmployeeLeave::create([
            'employee_id' => $this->employee->id,
            'starts_at' => $from->utc(),
            'ends_at' => $to->utc(),
        ]);
    }

    public function test_store_records_a_leave_and_writes_a_schedule_change_log_row(): void
    {
        $starts = CarbonImmutable::now()->addDay()->startOfHour();
        $ends = $starts->addHours(8);

        $response = $this->postJson("/api/v1/employees/{$this->employee->id}/leaves", [
            'starts_at' => $starts->toISOString(),
            'ends_at' => $ends->toISOString(),
            'note' => 'Family leave',
        ]);

        $response->assertCreated();
        $this->assertDatabaseCount('employee_leaves', 1);
        $this->assertSame(1, ScheduleChangeLog::where('target_employee_id', $this->employee->id)->count());
    }

    public function test_store_rejects_a_leave_starting_in_the_past(): void
    {
        $starts = CarbonImmutable::now()->subHour();

        $response = $this->postJson("/api/v1/employees/{$this->employee->id}/leaves", [
            'starts_at' => $starts->toISOString(),
            'ends_at' => $starts->addHours(4)->toISOString(),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['starts_at']);
    }

    public function test_store_rejects_an_end_before_the_start(): void
    {
        $starts = CarbonImmutable::now()->addDay();

        $response = $this->postJson("/api/v1/employees/{$this->employee->id}/leaves", [
            'starts_at' => $starts->toISOString(),
            'ends_at' => $starts->subHour()->toISOString(),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['ends_at']);
    }

    public function test_store_rejects_a_leave_overlapping_an_existing_one(): void
    {
        $starts = CarbonImmutable::now()->addDay()->startOfHour();
        $this->leave($starts, $starts->addHours(8));

        $response = $this->postJson("/api/v1/employees/{$this->employee->id}/leaves", [
            'starts_at' => $starts->addHours(4)->toISOString(),
            'ends_at' => $starts->addHours(12)->toISOString(),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['starts_at']);
    }

    public function test_index_returns_newest_first_in_pages_of_fifteen(): void
    {
        $base = CarbonImmutable::now()->addDay()->startOfHour();

        for ($i = 0; $i < 20; $i++) {
            $this->leave($base->addDays($i), $base->addDays($i)->addHours(2));
        }

        $response = $this->getJson("/api/v1/employees/{$this->employee->id}/leaves");

        $response->assertOk();
        $response->assertJsonCount(15, 'data');
        $this->assertSame($base->addDays(19)->utc()->toISOString(), $response->json('data.0.starts_at'));
        $this->assertSame(20, $response->json('meta.total'));

        $second = $this->getJson("/api/v1/employees/{$this->employee->id}/leaves?page=2");
        $second->assertOk();
        $second->assertJsonCount(5, 'data');
    }

    public function test_destroy_soft_deletes_the_leave_and_logs_it(): void
    {
        $starts = CarbonImmutable::now()->addDay()->startOfHour();
        $leave = $this->leave($starts, $starts->addHours(8));

        $response = $this->deleteJson("/api/v1/employee-leaves/{$leave->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('employee_leaves', ['id' => $leave->id]);
        $this->assertSame(1, ScheduleChangeLog::where('target_employee_id', $this->employee->id)->count());
    }

    public function test_a_supervisor_without_manage_schedules_cannot_record_a_leave(): void
    {
        $this->actingAs(User::factory()->supervisor()->create());

        $starts = CarbonImmutable::now()->addDay();

        $response = $this->postJson("/api/v1/employees/{$this->employee->id}/leaves", [
            'starts_at' => $starts->toISOString(),
            'ends_at' => $starts->addHours(4)->toISOString(),
        ]);

        $response->assertForbidden();
    }
}
