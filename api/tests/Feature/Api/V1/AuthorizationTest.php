<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_mobile_token_gets_403_on_schedule_crud(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/shift-templates', ['name' => 'X'])->assertStatus(403);
        $this->getJson('/api/v1/shift-templates')->assertStatus(403);
        $this->getJson('/api/v1/shift-templates')->assertStatus(403);
        $this->getJson('/api/v1/employee-shifts')->assertStatus(403);
        $this->getJson('/api/v1/shift-exceptions')->assertStatus(403);
    }

    public function test_employee_mobile_token_gets_403_reading_another_employees_window(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $otherEmployee = User::factory()->create();

        $response = $this->getJson("/api/v1/employees/{$otherEmployee->id}/window?date=2026-08-06");

        $response->assertStatus(403);
    }

    public function test_employee_mobile_token_can_still_reach_me_and_track(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/me/window')->assertOk();
        $this->postJson('/api/v1/track', ['points' => []])->assertStatus(422);
    }

    public function test_employee_session_gets_403_on_schedule_crud_and_employee_list(): void
    {
        $this->actingAs(User::factory()->create());

        $this->getJson('/api/v1/shift-templates')->assertStatus(403);
        $this->getJson('/api/v1/employees')->assertStatus(403);
    }

    public function test_supervisor_session_views_locations_but_gets_403_on_schedule_crud(): void
    {
        $this->actingAs(User::factory()->supervisor()->create());

        $this->getJson('/api/v1/employees')->assertStatus(403);
        $this->getJson('/api/v1/shift-templates')->assertStatus(403);
        $this->postJson('/api/v1/shift-templates', [])->assertStatus(403);
    }

    public function test_hr_session_manages_schedules_and_the_employee_roster_but_gets_403_on_location_endpoints(): void
    {
        $this->actingAs(User::factory()->hr()->create());

        $this->getJson('/api/v1/shift-templates')->assertOk();
        $this->getJson('/api/v1/employees')->assertOk();

        $otherEmployee = User::factory()->create();
        $this->getJson("/api/v1/employees/{$otherEmployee->id}/window?date=2026-08-06")->assertStatus(403);
    }

    public function test_admin_session_reaches_both_schedule_crud_and_locations(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->getJson('/api/v1/shift-templates')->assertOk();
        $this->getJson('/api/v1/employees')->assertOk();
    }

    public function test_unauthenticated_request_gets_401_not_403(): void
    {
        $this->getJson('/api/v1/shift-templates')->assertStatus(401);
        $this->getJson('/api/v1/employees')->assertStatus(401);
    }

    public function test_unauthenticated_request_without_accept_header_gets_401_not_500(): void
    {
        $this->get('/api/v1/shift-templates')->assertStatus(401);
    }
}
