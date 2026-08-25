<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Notifications\CaseAssignedNotification;
use App\Services\CaseLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_case(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $response = $this->postJson('/api/v1/cases', [
            'reference_no' => 'INS-100',
            'title' => 'Villa valuation',
            'property_address' => 'Seeb',
            'lat' => 23.55,
            'lng' => 58.35,
            'priority' => 'high',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('reference_no', 'INS-100');
        $response->assertJsonPath('status', 'pending');
        $this->assertDatabaseHas('inspection_cases', ['reference_no' => 'INS-100']);
    }

    public function test_employee_without_capability_cannot_create_a_case(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->postJson('/api/v1/cases', [
            'reference_no' => 'INS-101',
            'title' => 'Villa valuation',
            'lat' => 23.55,
            'lng' => 58.35,
        ]);

        $response->assertForbidden();
    }

    public function test_assigning_a_pending_case_notifies_the_employee_and_logs_the_event(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->create();

        $case = app(CaseLifecycleService::class)->create([
            'reference_no' => 'INS-102',
            'title' => 'Apartment valuation',
            'property_address' => null,
            'lat' => 23.55,
            'lng' => 58.35,
            'priority' => 'normal',
        ], $admin);

        $this->actingAs($admin);
        $response = $this->postJson("/api/v1/cases/{$case->id}/assign", ['employee_id' => $employee->id]);

        $response->assertOk();
        $response->assertJsonPath('assigned_to', $employee->id);
        $this->assertDatabaseHas('case_status_events', ['inspection_case_id' => $case->id]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $employee->id,
            'type' => CaseAssignedNotification::class,
        ]);
    }

    public function test_an_assignment_awaiting_acceptance_cannot_be_replaced(): void
    {
        $admin = User::factory()->admin()->create();
        $employeeA = User::factory()->create();
        $employeeB = User::factory()->create();

        $lifecycle = app(CaseLifecycleService::class);
        $case = $lifecycle->create([
            'reference_no' => 'INS-110',
            'title' => 'Reassignment case',
            'property_address' => null,
            'lat' => 23.55,
            'lng' => 58.35,
            'priority' => 'normal',
        ], $admin);

        $case = $lifecycle->assign($case, $employeeA, $admin);

        $this->actingAs($admin);
        $response = $this->postJson("/api/v1/cases/{$case->id}/assign", ['employee_id' => $employeeB->id]);

        $response->assertStatus(409);
        $response->assertJsonPath('message', 'This assignment is awaiting the surveyor response and cannot be replaced yet.');
        $this->assertDatabaseMissing('case_status_events', [
            'inspection_case_id' => $case->id,
            'note' => "Reassigned from {$employeeA->name} to {$employeeB->name}.",
        ]);
    }

    public function test_assign_sets_assigned_at_matching_the_logged_events_created_at(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->create();

        $lifecycle = app(CaseLifecycleService::class);
        $case = $lifecycle->create([
            'reference_no' => 'INS-111',
            'title' => 'Timestamp case',
            'property_address' => null,
            'lat' => 23.55,
            'lng' => 58.35,
            'priority' => 'normal',
        ], $admin);

        $case = $lifecycle->assign($case, $employee, $admin);

        $event = $case->statusEvents()->latest('created_at')->first();

        $this->assertNotNull($event);
        $this->assertTrue($case->assigned_at->equalTo($event->created_at));
    }

    public function test_creating_a_case_notifies_every_active_employee(): void
    {
        $admin = User::factory()->admin()->create();
        $activeEmployee = User::factory()->create(['is_active' => true]);
        $inactiveEmployee = User::factory()->create(['is_active' => false]);

        $this->actingAs($admin);
        $response = $this->postJson('/api/v1/cases', [
            'reference_no' => 'INS-112',
            'title' => 'Notify-all case',
            'lat' => 23.55,
            'lng' => 58.35,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $activeEmployee->id]);
        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $inactiveEmployee->id]);
    }

    public function test_new_case_cannot_be_assigned_in_the_create_request(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->create();

        $this->actingAs($admin);
        $response = $this->postJson('/api/v1/cases', [
            'reference_no' => 'INS-113',
            'title' => 'Create separately from assign',
            'lat' => 23.55,
            'lng' => 58.35,
            'assigned_to' => $employee->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('assigned_to', null);
        $this->assertDatabaseHas('inspection_cases', [
            'reference_no' => 'INS-113',
            'assigned_to' => null,
        ]);
    }

    public function test_an_already_accepted_case_cannot_be_reassigned(): void
    {
        $admin = User::factory()->admin()->create();
        $employeeA = User::factory()->create();
        $employeeB = User::factory()->create();

        $lifecycle = app(CaseLifecycleService::class);
        $case = $lifecycle->create([
            'reference_no' => 'INS-103',
            'title' => 'Land valuation',
            'property_address' => null,
            'lat' => 23.55,
            'lng' => 58.35,
            'priority' => 'normal',
        ], $admin);
        $case = $lifecycle->assign($case, $employeeA, $admin);
        $lifecycle->accept($case, $employeeA, now()->addDay());

        $this->actingAs($admin);
        $response = $this->postJson("/api/v1/cases/{$case->id}/assign", ['employee_id' => $employeeB->id]);

        $response->assertStatus(409);
    }

    public function test_destroy_refuses_a_case_that_is_no_longer_pending(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->create();

        $lifecycle = app(CaseLifecycleService::class);
        $case = $lifecycle->create([
            'reference_no' => 'INS-104',
            'title' => 'Shop valuation',
            'property_address' => null,
            'lat' => 23.55,
            'lng' => 58.35,
            'priority' => 'normal',
        ], $admin);
        $lifecycle->assign($case, $employee, $admin);
        $lifecycle->accept($case->fresh(), $employee, now()->addDay());

        $this->actingAs($admin);
        $response = $this->deleteJson("/api/v1/cases/{$case->id}");

        $response->assertStatus(409);
        $this->assertDatabaseHas('inspection_cases', ['id' => $case->id]);
    }

    public function test_destroy_refuses_an_assignment_awaiting_acceptance(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->create();
        $case = app(CaseLifecycleService::class)->create([
            'reference_no' => 'INS-DELETE-ASSIGNED',
            'title' => 'Assigned case',
            'property_address' => null,
            'lat' => 23.55,
            'lng' => 58.35,
            'priority' => 'normal',
        ], $admin);
        app(CaseLifecycleService::class)->assign($case, $employee, $admin);

        $this->actingAs($admin)
            ->deleteJson("/api/v1/cases/{$case->id}")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Only unaccepted, unassigned cases can be deleted.');

        $this->assertDatabaseHas('inspection_cases', ['id' => $case->id]);
    }

    public function test_assigning_to_a_deactivated_employee_is_refused_with_a_message(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->create(['is_active' => false]);

        $case = app(CaseLifecycleService::class)->create([
            'reference_no' => 'INS-105',
            'title' => 'Warehouse valuation',
            'property_address' => null,
            'lat' => 23.55,
            'lng' => 58.35,
            'priority' => 'normal',
        ], $admin);

        $this->actingAs($admin);
        $response = $this->postJson("/api/v1/cases/{$case->id}/assign", ['employee_id' => $employee->id]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('employee_id');
    }

    public function test_a_rejected_case_can_be_reassigned(): void
    {
        $admin = User::factory()->admin()->create();
        $employeeA = User::factory()->create();
        $employeeB = User::factory()->create();

        $lifecycle = app(CaseLifecycleService::class);
        $case = $lifecycle->create([
            'reference_no' => 'INS-106',
            'title' => 'Office valuation',
            'property_address' => null,
            'lat' => 23.55,
            'lng' => 58.35,
            'priority' => 'normal',
        ], $admin);
        $case = $lifecycle->assign($case, $employeeA, $admin);
        $lifecycle->reject($case, $employeeA, 'Too far.');

        $this->actingAs($admin);
        $response = $this->postJson("/api/v1/cases/{$case->id}/assign", ['employee_id' => $employeeB->id]);

        $response->assertOk();
        $response->assertJsonPath('status', 'pending');
        $response->assertJsonPath('assigned_to', $employeeB->id);
    }
}
