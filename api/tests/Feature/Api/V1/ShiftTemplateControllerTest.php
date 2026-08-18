<?php

namespace Tests\Feature\Api\V1;

use App\Models\EmployeeShift;
use App\Models\ScheduleChangeLog;
use App\Models\ShiftTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftTemplateControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_every_template_ordered_by_name(): void
    {
        $this->actingAs(User::factory()->hr()->create());
        ShiftTemplate::factory()->create(['name' => 'Night']);
        ShiftTemplate::factory()->create(['name' => 'Day']);

        $response = $this->getJson('/api/v1/shift-templates');

        $response->assertOk();
        $response->assertJsonCount(2);
        $response->assertJsonPath('0.name', 'Day');
        $response->assertJsonPath('1.name', 'Night');
    }

    public function test_store_creates_a_template(): void
    {
        $this->actingAs(User::factory()->hr()->create());

        $response = $this->postJson('/api/v1/shift-templates', [
            'name' => 'Standard',
            'days_of_week' => [0, 1, 2, 3, 4],
            'start_time' => '07:00',
            'end_time' => '16:00',
            'grace_before_min' => 10,
            'grace_after_min' => 15,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('shift_templates', ['name' => 'Standard']);
    }

    public function test_store_rejects_a_day_of_week_out_of_range(): void
    {
        $this->actingAs(User::factory()->hr()->create());

        $response = $this->postJson('/api/v1/shift-templates', [
            'name' => 'Standard',
            'days_of_week' => [7],
            'start_time' => '07:00',
            'end_time' => '16:00',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['days_of_week.0']);
    }

    public function test_update_and_destroy(): void
    {
        $this->actingAs(User::factory()->hr()->create());
        $template = ShiftTemplate::factory()->create();

        $update = $this->putJson("/api/v1/shift-templates/{$template->id}", [
            'name' => 'Renamed',
            'days_of_week' => [0, 1, 2, 3, 4],
            'start_time' => '08:00',
            'end_time' => '17:00',
        ]);
        $update->assertOk();
        $this->assertDatabaseHas('shift_templates', ['id' => $template->id, 'name' => 'Renamed']);

        $destroy = $this->deleteJson("/api/v1/shift-templates/{$template->id}");
        $destroy->assertNoContent();
        $this->assertDatabaseMissing('shift_templates', ['id' => $template->id]);
    }

    public function test_destroy_removes_assigned_employees_shifts_and_logs_each_one(): void
    {
        $this->actingAs(User::factory()->hr()->create());
        $template = ShiftTemplate::factory()->create();
        $employee = User::factory()->create();
        $shift = EmployeeShift::factory()->create([
            'employee_id' => $employee->id,
            'template_id' => $template->id,
        ]);

        $destroy = $this->deleteJson("/api/v1/shift-templates/{$template->id}");

        $destroy->assertNoContent();
        $this->assertDatabaseMissing('shift_templates', ['id' => $template->id]);
        $this->assertDatabaseMissing('employee_shifts', ['id' => $shift->id]);

        $log = ScheduleChangeLog::where('target_employee_id', $employee->id)->first();
        $this->assertNotNull($log);
        $this->assertSame($template->id, $log->before['template_id']);
        $this->assertNull($log->after);
    }
}
