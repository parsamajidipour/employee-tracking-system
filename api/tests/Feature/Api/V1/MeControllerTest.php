<?php

namespace Tests\Feature\Api\V1;

use App\Models\ShiftTemplate;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MeControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    private CarbonImmutable $sunday;

    protected function setUp(): void
    {
        parent::setUp();

        $team = Team::factory()->create(['timezone' => 'Asia/Muscat']);
        ShiftTemplate::factory()->create([
            'team_id' => $team->id,
            'grace_before_min' => 10,
            'grace_after_min' => 15,
        ]); // Sun-Thu 07:00-16:00, graced 06:50-16:15
        $this->employee = User::factory()->create(['team_id' => $team->id]);

        $this->sunday = CarbonImmutable::parse('next Sunday', 'Asia/Muscat')->startOfDay();

        Sanctum::actingAs($this->employee);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_current_window_is_null_and_next_window_is_todays_later_window(): void
    {
        $thursday = $this->sunday->addDays(4);
        CarbonImmutable::setTestNow($thursday->setTime(5, 0)); // before today's graced start (06:50)

        $response = $this->getJson('/api/v1/me/window');

        $response->assertOk();
        $response->assertJson(['current' => null]);
        $response->assertJsonPath('next.start', $thursday->setTime(6, 50)->utc()->toISOString());
        $response->assertJsonPath('next.end', $thursday->setTime(16, 15)->utc()->toISOString());
        $response->assertJsonPath('next.source', 'team_template');
    }

    public function test_current_window_exposes_graced_times_when_inside_a_window(): void
    {
        $thursday = $this->sunday->addDays(4);
        CarbonImmutable::setTestNow($thursday->setTime(10, 0));

        $response = $this->getJson('/api/v1/me/window');

        $response->assertOk();
        $response->assertJsonPath('current.start', $thursday->setTime(6, 50)->utc()->toISOString());
        $response->assertJsonPath('current.end', $thursday->setTime(16, 15)->utc()->toISOString());
    }
}
