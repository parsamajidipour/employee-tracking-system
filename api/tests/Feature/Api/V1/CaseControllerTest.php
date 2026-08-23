<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
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
        $this->assertDatabaseCount('notifications', 1);
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
}
