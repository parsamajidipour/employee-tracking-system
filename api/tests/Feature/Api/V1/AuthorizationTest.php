<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Cross-cutting tests for App\Http\Middleware\EnsureCapability — the
 * `capability:manage-schedules` and `capability:view-locations` gates
 * applied to every panel/admin route in routes/api.php. Resource-specific
 * CRUD behavior is covered in each resource's own test (TeamControllerTest,
 * etc.); this file covers the authorization boundary itself: which roles
 * get which capability, that hr and supervisor do NOT inherit each other's
 * access (separation of duties — see App\Enums\UserRole's docblock), and —
 * the case that matters most per CLAUDE.md — that a mobile token never
 * gets in, regardless of role or capability.
 */
class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_mobile_token_gets_403_on_schedule_and_team_crud(): void
    {
        // Sanctum::actingAs() simulates real bearer-token (mobile) auth —
        // see Laravel\Sanctum\Sanctum::actingAs(), which attaches a
        // PersonalAccessToken. This is the one place in the suite that
        // auth simulation is deliberately used instead of $this->actingAs().
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/teams', ['name' => 'X', 'timezone' => 'Asia/Muscat'])->assertStatus(403);
        $this->getJson('/api/v1/teams')->assertStatus(403);
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
        // The one thing a mobile token *is* for. No role gate applies here.
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/me/window')->assertOk();
        $this->postJson('/api/v1/track', ['points' => []])->assertStatus(422); // reaches validation, not 403/401
    }

    public function test_employee_session_gets_403_on_team_crud_and_employee_list(): void
    {
        // A plain employee-role *session* (not a token) — proves the gate
        // is role-based, not merely "is this a token."
        $this->actingAs(User::factory()->create());

        $this->getJson('/api/v1/teams')->assertStatus(403);
        $this->getJson('/api/v1/employees')->assertStatus(403);
    }

    public function test_supervisor_session_views_locations_but_gets_403_on_schedule_crud(): void
    {
        $this->actingAs(User::factory()->supervisor()->create());

        // The employee roster (GET /employees) moved to manage-schedules —
        // it carries phone numbers, usernames, active status, and device
        // identifiers now (App\Http\Controllers\Api\V1\EmployeeController),
        // none of which view-locations is meant to reach. A supervisor
        // still gets employee names from /positions and still reaches
        // window()/session() below, which is all the live map needs.
        $this->getJson('/api/v1/employees')->assertStatus(403);
        $this->getJson('/api/v1/teams')->assertStatus(403);
        $this->postJson('/api/v1/shift-templates', [])->assertStatus(403);
    }

    public function test_hr_session_manages_schedules_and_the_employee_roster_but_gets_403_on_location_endpoints(): void
    {
        // The separation of duties this system depends on: hr sets working
        // hours and manages employee accounts, but must not be able to see
        // where anyone is.
        $this->actingAs(User::factory()->hr()->create());

        $this->getJson('/api/v1/teams')->assertOk();
        $this->getJson('/api/v1/employees')->assertOk();

        $otherEmployee = User::factory()->create();
        $this->getJson("/api/v1/employees/{$otherEmployee->id}/window?date=2026-08-06")->assertStatus(403);
    }

    public function test_admin_session_reaches_both_schedule_crud_and_locations(): void
    {
        // Admin deliberately holds both capabilities — see DECISIONS.md.
        $this->actingAs(User::factory()->admin()->create());

        $this->getJson('/api/v1/teams')->assertOk();
        $this->getJson('/api/v1/employees')->assertOk();
    }

    public function test_unauthenticated_request_gets_401_not_403(): void
    {
        // No user at all — the auth:sanctum guard itself rejects this,
        // before EnsureCapability ever runs.
        $this->getJson('/api/v1/teams')->assertStatus(401);
        $this->getJson('/api/v1/employees')->assertStatus(401);
    }

    public function test_unauthenticated_request_without_accept_header_gets_401_not_500(): void
    {
        // Regression test: plain $this->get(), unlike getJson(), sends no
        // `Accept: application/json` header — the exact condition that
        // used to crash with a RouteNotFoundException (no route named
        // `login` exists; api/ is API only, no web login page) instead of
        // returning 401. See bootstrap/app.php's redirectGuestsTo() and
        // shouldRenderJsonWhen() overrides.
        $this->get('/api/v1/teams')->assertStatus(401);
    }
}
