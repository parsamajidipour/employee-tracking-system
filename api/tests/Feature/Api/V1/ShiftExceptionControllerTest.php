<?php

namespace Tests\Feature\Api\V1;

use App\Models\ScheduleChangeLog;
use App\Models\ShiftException;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftExceptionControllerTest extends TestCase
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

    public function test_index_filters_by_employee_id(): void
    {
        ShiftException::factory()->leave()->create(['employee_id' => $this->employee->id]);
        ShiftException::factory()->leave()->create();

        $response = $this->getJson("/api/v1/shift-exceptions?employee_id={$this->employee->id}");

        $response->assertOk();
        $response->assertJsonCount(1);
    }

    public function test_store_a_leave_exception_forces_times_to_null_and_logs_with_no_effective_from(): void
    {
        $response = $this->postJson('/api/v1/shift-exceptions', [
            'employee_id' => $this->employee->id,
            'date' => CarbonImmutable::now()->addDay()->toDateString(),
            'type' => 'leave',
            'start_at' => '09:00',
            'end_at' => '10:00',
            'reason' => 'Annual leave',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('shift_exceptions', [
            'employee_id' => $this->employee->id,
            'type' => 'leave',
            'start_at' => null,
            'end_at' => null,
        ]);

        $log = ScheduleChangeLog::first();
        $this->assertNotNull($log);
        $this->assertNull($log->before);
        $this->assertSame('leave', $log->after['type']);
        $this->assertNull($log->effective_from);
        $this->assertSame('Annual leave', $log->reason);
    }

    public function test_store_an_overtime_exception_requires_start_and_end_times(): void
    {
        $response = $this->postJson('/api/v1/shift-exceptions', [
            'employee_id' => $this->employee->id,
            'date' => CarbonImmutable::now()->addDay()->toDateString(),
            'type' => 'overtime',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['start_at', 'end_at']);
    }

    public function test_store_an_overtime_exception_with_times(): void
    {
        $response = $this->postJson('/api/v1/shift-exceptions', [
            'employee_id' => $this->employee->id,
            'date' => CarbonImmutable::now()->addDay()->toDateString(),
            'type' => 'overtime',
            'start_at' => '16:00',
            'end_at' => '20:00',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('shift_exceptions', [
            'employee_id' => $this->employee->id,
            'type' => 'overtime',
        ]);
    }

    public function test_update_switching_to_leave_clears_previously_set_times(): void
    {
        $exception = ShiftException::factory()->overtime('16:00:00', '20:00:00')->create([
            'employee_id' => $this->employee->id,
        ]);

        $response = $this->putJson("/api/v1/shift-exceptions/{$exception->id}", [
            'type' => 'leave',
        ]);

        $response->assertOk();
        $exception->refresh();
        $this->assertNull($exception->start_at);
        $this->assertNull($exception->end_at);

        $log = ScheduleChangeLog::first();
        $this->assertSame('overtime', $log->before['type']);
        $this->assertSame('leave', $log->after['type']);
    }

    public function test_destroy_removes_the_row_and_logs_it(): void
    {
        $exception = ShiftException::factory()->leave()->create(['employee_id' => $this->employee->id]);

        $response = $this->deleteJson("/api/v1/shift-exceptions/{$exception->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('shift_exceptions', ['id' => $exception->id]);

        $log = ScheduleChangeLog::first();
        $this->assertNotNull($log->before);
        $this->assertNull($log->after);
    }
}
