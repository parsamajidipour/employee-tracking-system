<?php

namespace Tests\Feature\Api\V1;

use App\Models\EmployeeShift;
use App\Models\ScheduleChangeLog;
use App\Models\ShiftTemplate;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeShiftControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $employee;

    private ShiftTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->hr()->create();
        $team = Team::factory()->create();
        $this->template = ShiftTemplate::factory()->create(['team_id' => $team->id]);
        $this->employee = User::factory()->create(['team_id' => $team->id]);

        $this->actingAs($this->admin);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_index_filters_by_employee_id(): void
    {
        EmployeeShift::factory()->create(['employee_id' => $this->employee->id, 'template_id' => $this->template->id]);
        EmployeeShift::factory()->create(); // a different employee

        $response = $this->getJson("/api/v1/employee-shifts?employee_id={$this->employee->id}");

        $response->assertOk();
        $response->assertJsonCount(1);
    }

    public function test_store_creates_a_row_and_writes_an_append_only_log_entry(): void
    {
        // startOfSecond(): Postgres `timestamp` storage (via Laravel's
        // default cast format) truncates microseconds, so comparing a
        // round-tripped value against one that still carries them would
        // fail equalTo() on precision alone, not on anything meaningful.
        $effectiveFrom = CarbonImmutable::now()->addDay()->startOfSecond();

        $response = $this->postJson('/api/v1/employee-shifts', [
            'employee_id' => $this->employee->id,
            'template_id' => $this->template->id,
            'effective_from' => $effectiveFrom->toISOString(),
            'reason' => 'Promoted to day shift',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('employee_shifts', [
            'employee_id' => $this->employee->id,
            'template_id' => $this->template->id,
        ]);

        $log = ScheduleChangeLog::first();
        $this->assertNotNull($log);
        $this->assertSame($this->admin->id, $log->actor_id);
        $this->assertSame($this->employee->id, $log->target_employee_id);
        $this->assertNull($log->before);
        $this->assertSame($this->template->id, $log->after['template_id']);
        $this->assertSame('Promoted to day shift', $log->reason);
        $this->assertTrue($log->effective_from->equalTo($effectiveFrom->utc()));
    }

    public function test_store_rejects_an_effective_from_in_the_past(): void
    {
        $response = $this->postJson('/api/v1/employee-shifts', [
            'employee_id' => $this->employee->id,
            'template_id' => $this->template->id,
            'effective_from' => CarbonImmutable::now()->subDay()->toISOString(),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['effective_from']);
        $this->assertDatabaseCount('employee_shifts', 0);
        $this->assertDatabaseCount('schedule_change_log', 0);
    }

    public function test_update_changes_the_row_and_logs_before_and_after(): void
    {
        $shift = EmployeeShift::factory()->create([
            'employee_id' => $this->employee->id,
            'template_id' => $this->template->id,
            'effective_to' => null,
        ]);
        $newEffectiveTo = CarbonImmutable::now()->addWeek()->startOfSecond();

        $response = $this->putJson("/api/v1/employee-shifts/{$shift->id}", [
            'effective_to' => $newEffectiveTo->toISOString(),
            'reason' => 'Ending the assignment',
        ]);

        $response->assertOk();
        $shift->refresh();
        $this->assertTrue($shift->effective_to->equalTo($newEffectiveTo->utc()));

        $log = ScheduleChangeLog::first();
        $this->assertNotNull($log);
        $this->assertNull($log->before['effective_to']);
        $this->assertNotNull($log->after['effective_to']);
        $this->assertSame('Ending the assignment', $log->reason);
    }

    public function test_update_rejects_changing_effective_from_into_the_past(): void
    {
        $shift = EmployeeShift::factory()->create([
            'employee_id' => $this->employee->id,
            'template_id' => $this->template->id,
        ]);

        $response = $this->putJson("/api/v1/employee-shifts/{$shift->id}", [
            'effective_from' => CarbonImmutable::now()->subWeek()->toISOString(),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['effective_from']);
    }

    public function test_update_without_effective_from_succeeds_even_though_the_existing_value_is_already_past(): void
    {
        // The row's own effective_from is in the past (it's already active)
        // — invariant 6 only blocks *setting* a past effective_from, not
        // editing an otherwise-unrelated field on a row that already has one.
        $shift = EmployeeShift::factory()->create([
            'employee_id' => $this->employee->id,
            'template_id' => $this->template->id,
            'effective_from' => CarbonImmutable::now()->subMonth()->utc(),
            'effective_to' => null,
        ]);

        $response = $this->putJson("/api/v1/employee-shifts/{$shift->id}", [
            'effective_to' => CarbonImmutable::now()->addDay()->toISOString(),
        ]);

        $response->assertOk();
    }

    public function test_destroy_removes_the_row_and_logs_before_with_null_after(): void
    {
        $shift = EmployeeShift::factory()->create([
            'employee_id' => $this->employee->id,
            'template_id' => $this->template->id,
        ]);

        $response = $this->deleteJson("/api/v1/employee-shifts/{$shift->id}?reason=".urlencode('No longer needed'));

        $response->assertNoContent();
        $this->assertDatabaseMissing('employee_shifts', ['id' => $shift->id]);

        $log = ScheduleChangeLog::first();
        $this->assertNotNull($log);
        $this->assertNotNull($log->before);
        $this->assertNull($log->after);
        $this->assertSame('No longer needed', $log->reason);
    }
}
