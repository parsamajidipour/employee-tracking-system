<?php

namespace Tests\Feature\Api\V1;

use App\Models\ShiftTemplate;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_employees(): void
    {
        $this->actingAs(User::factory()->supervisor()->create());
        $team = Team::factory()->create();
        $employee = User::factory()->create(['team_id' => $team->id, 'name' => 'Fahad Al-Balushi']);

        $response = $this->getJson('/api/v1/employees');

        $response->assertOk();
        $response->assertJsonFragment(['id' => $employee->id, 'name' => 'Fahad Al-Balushi']);
    }

    public function test_window_returns_the_resolved_window_for_the_chosen_date(): void
    {
        $this->actingAs(User::factory()->supervisor()->create());
        $team = Team::factory()->create(['timezone' => 'Asia/Muscat']);
        ShiftTemplate::factory()->create(['team_id' => $team->id]); // Sun-Thu 07:00-16:00
        $employee = User::factory()->create(['team_id' => $team->id]);

        $sunday = CarbonImmutable::parse('next Sunday', 'Asia/Muscat')->startOfDay();
        $thursday = $sunday->addDays(4);

        $response = $this->getJson("/api/v1/employees/{$employee->id}/window?date={$thursday->toDateString()}");

        $response->assertOk();
        $response->assertJsonPath('window.source', 'team_template');
        $response->assertJsonPath('window.start', $thursday->setTime(7, 0)->utc()->toISOString());
    }

    public function test_window_is_null_on_a_weekend_day_under_a_sunday_thursday_template(): void
    {
        $this->actingAs(User::factory()->supervisor()->create());
        $team = Team::factory()->create(['timezone' => 'Asia/Muscat']);
        ShiftTemplate::factory()->create(['team_id' => $team->id]); // Sun-Thu
        $employee = User::factory()->create(['team_id' => $team->id]);

        $sunday = CarbonImmutable::parse('next Sunday', 'Asia/Muscat')->startOfDay();
        $friday = $sunday->addDays(5);

        $response = $this->getJson("/api/v1/employees/{$employee->id}/window?date={$friday->toDateString()}");

        $response->assertOk();
        $response->assertJson(['window' => null]);
    }
}
