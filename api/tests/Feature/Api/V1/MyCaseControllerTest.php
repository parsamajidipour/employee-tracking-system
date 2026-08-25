<?php

namespace Tests\Feature\Api\V1;

use App\Enums\CaseStatus;
use App\Models\CasePhoto;
use App\Models\InspectionCase;
use App\Models\User;
use App\Services\CaseLifecycleService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MyCaseControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeCase(User $admin, ?User $assignee = null): InspectionCase
    {
        $lifecycle = app(CaseLifecycleService::class);
        $case = $lifecycle->create([
            'reference_no' => 'INS-'.uniqid(),
            'title' => 'Villa valuation',
            'property_address' => null,
            'lat' => 23.55,
            'lng' => 58.35,
            'priority' => 'normal',
        ], $admin);

        if ($assignee !== null) {
            $case = $lifecycle->assign($case, $assignee, $admin);
        }

        return $case;
    }

    public function test_employee_sees_only_their_own_assigned_cases(): void
    {
        $admin = User::factory()->admin()->create();
        $mine = User::factory()->create();
        $someoneElse = User::factory()->create();

        $this->makeCase($admin, $mine);
        $this->makeCase($admin, $someoneElse);

        $this->actingAs($mine);
        $response = $this->getJson('/api/v1/me/cases');

        $response->assertOk();
        $this->assertCount(1, $response->json());
    }

    public function test_accept_sets_planned_at_and_transitions_to_accepted(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->create();
        $case = $this->makeCase($admin, $employee);

        $plannedAt = CarbonImmutable::now()->addDay();

        $this->actingAs($employee);
        $response = $this->postJson("/api/v1/me/cases/{$case->id}/accept", ['planned_at' => $plannedAt->toISOString()]);

        $response->assertOk();
        $response->assertJsonPath('status', 'accepted');
        $this->assertDatabaseHas('inspection_cases', ['id' => $case->id, 'status' => CaseStatus::Accepted->value]);
    }

    public function test_employee_cannot_act_on_a_case_assigned_to_someone_else(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $case = $this->makeCase($admin, $owner);

        $this->actingAs($intruder);
        $response = $this->postJson("/api/v1/me/cases/{$case->id}/accept", ['planned_at' => now()->addDay()->toISOString()]);

        $response->assertForbidden();
    }

    public function test_cannot_start_a_case_that_has_not_been_accepted(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->create();
        $case = $this->makeCase($admin, $employee);

        $this->actingAs($employee);
        $response = $this->postJson("/api/v1/me/cases/{$case->id}/start");

        $response->assertStatus(409);
    }

    public function test_full_lifecycle_pending_to_completed(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->create();
        $case = $this->makeCase($admin, $employee);

        $this->actingAs($employee);

        $this->postJson("/api/v1/me/cases/{$case->id}/accept", ['planned_at' => now()->addHour()->toISOString()])->assertOk();
        $this->postJson("/api/v1/me/cases/{$case->id}/start")->assertOk();
        CasePhoto::create([
            'inspection_case_id' => $case->id,
            'employee_id' => $employee->id,
            'disk_path' => 'case-photos/test.png',
            'location' => DB::raw('ST_SetSRID(ST_MakePoint(58.35, 23.55), 4326)::geography'),
            'accuracy_m' => 5,
            'distance_from_case_m' => 0,
            'is_gps_verified' => true,
            'captured_at' => now(),
        ]);
        $response = $this->postJson("/api/v1/me/cases/{$case->id}/complete", ['note' => 'Done.']);

        $response->assertOk();
        $response->assertJsonPath('status', 'completed');
        $this->assertSame(5, $case->statusEvents()->count());
    }

    public function test_case_cannot_be_completed_without_a_gps_verified_site_photo(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->create();
        $case = $this->makeCase($admin, $employee);

        $this->actingAs($employee);
        $this->postJson("/api/v1/me/cases/{$case->id}/accept", ['planned_at' => now()->addHour()->toISOString()])->assertOk();
        $this->postJson("/api/v1/me/cases/{$case->id}/start")->assertOk();

        $response = $this->postJson("/api/v1/me/cases/{$case->id}/complete", ['note' => 'Done.']);

        $response->assertStatus(409);
        $response->assertJsonPath('message', 'Add at least one GPS-verified site photo before completing the inspection.');
        $this->assertDatabaseHas('inspection_cases', ['id' => $case->id, 'status' => CaseStatus::InProgress->value]);
    }

    public function test_scheduler_marks_past_scheduled_case_overdue_and_notifies_the_surveyor(): void
    {
        $this->travelTo(now()->startOfHour());
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->create();
        $case = $this->makeCase($admin, $employee);
        app(CaseLifecycleService::class)->accept($case, $employee, now()->addMinutes(5));

        $this->travel(6)->minutes();
        $this->artisan('cases:mark-overdue')->assertSuccessful();

        $this->assertDatabaseHas('inspection_cases', ['id' => $case->id, 'status' => CaseStatus::Overdue->value]);
        $this->assertDatabaseHas('case_status_events', [
            'inspection_case_id' => $case->id,
            'from_status' => CaseStatus::Accepted->value,
            'to_status' => CaseStatus::Overdue->value,
        ]);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $employee->id]);
        $this->travelBack();
    }
}
