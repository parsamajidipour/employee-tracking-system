<?php

namespace Tests\Feature\Api\V1;

use App\Models\ShiftException;
use App\Models\ShiftTemplate;
use App\Models\Team;
use App\Models\User;
use App\Services\TrackingGate;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class PositionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // RefreshDatabase resets the users id sequence back to 1 at the
        // start of every fresh `php artisan test` run, but Redis (isolated
        // to its own REDIS_DB, see phpunit.xml, but never wiped) persists
        // across runs — without this, a `last_known:1` key left over from
        // an earlier invocation collides with today's employee id 1 and
        // makes this test's outcome depend on what a previous run happened
        // to leave behind.
        Redis::connection()->flushdb();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_snapshot_excludes_employees_outside_their_window(): void
    {
        $team = Team::factory()->create(['timezone' => 'Asia/Muscat']);
        ShiftTemplate::factory()->create(['team_id' => $team->id]); // Sun-Thu 07:00-16:00

        $inWindowEmployee = User::factory()->create(['team_id' => $team->id]);
        $onLeaveEmployee = User::factory()->create(['team_id' => $team->id]);

        $sunday = CarbonImmutable::parse('next Sunday', 'Asia/Muscat')->startOfDay();
        $thursday = $sunday->addDays(4);

        // On leave for the same date the team template would otherwise put
        // them inside a window — the resolver's absolute veto (CLAUDE.md
        // invariant 3/8) is what must exclude them, not merely "no team."
        ShiftException::factory()->leave()->create([
            'employee_id' => $onLeaveEmployee->id,
            'date' => $thursday->toDateString(),
        ]);

        CarbonImmutable::setTestNow($thursday->setTime(10, 0));

        // Accepts for real through App\Services\TrackingGate, so the
        // last_known Redis key is written by the same path production
        // traffic takes, not hand-seeded.
        app(TrackingGate::class)->process($inWindowEmployee, [[
            'lat' => 23.5,
            'lng' => 58.4,
            'accuracy_m' => 5.0,
            'speed_mps' => 0.0,
            'heading_deg' => 0.0,
            'battery_pct' => 80,
            'is_mocked' => false,
            'recorded_at' => $thursday->setTime(10, 0)->toISOString(),
        ]]);

        // Directly seed a cached position for the on-leave employee too —
        // proving the endpoint's own resolver re-check at read time is what
        // excludes them (App\Services\PositionPublisher's "second line of
        // defence" TTL is not what's being tested here; a live, unexpired
        // key must still not be enough on its own).
        Redis::setex("last_known:{$onLeaveEmployee->id}", 60, json_encode([
            'employee_id' => $onLeaveEmployee->id,
            'lat' => 23.6,
            'lng' => 58.5,
            'recorded_at' => $thursday->setTime(10, 0)->toISOString(),
        ]));

        $this->actingAs(User::factory()->admin()->create());

        $response = $this->getJson('/api/v1/positions');

        $response->assertOk();
        $ids = collect($response->json())->pluck('employee_id')->all();

        $this->assertContains($inWindowEmployee->id, $ids);
        $this->assertNotContains($onLeaveEmployee->id, $ids);
    }

    public function test_snapshot_row_includes_name_team_telemetry_and_effective_end(): void
    {
        $team = Team::factory()->create(['timezone' => 'Asia/Muscat', 'name' => 'Field Ops']);
        ShiftTemplate::factory()->create(['team_id' => $team->id]); // Sun-Thu 07:00-16:00, no grace

        $employee = User::factory()->create(['team_id' => $team->id, 'name' => 'Fahad']);

        $sunday = CarbonImmutable::parse('next Sunday', 'Asia/Muscat')->startOfDay();
        $thursday = $sunday->addDays(4);
        CarbonImmutable::setTestNow($thursday->setTime(10, 0));

        app(TrackingGate::class)->process($employee, [[
            'lat' => 23.5,
            'lng' => 58.4,
            'accuracy_m' => 12.5,
            'speed_mps' => 0.0,
            'heading_deg' => 0.0,
            'battery_pct' => 42,
            'is_mocked' => false,
            'recorded_at' => $thursday->setTime(10, 0)->toISOString(),
        ]]);

        $this->actingAs(User::factory()->admin()->create());

        $response = $this->getJson('/api/v1/positions');

        $response->assertOk();
        $row = collect($response->json())->firstWhere('employee_id', $employee->id);

        $this->assertNotNull($row);
        $this->assertSame('Fahad', $row['name']);
        $this->assertSame('Field Ops', $row['team_name']);
        $this->assertEquals(12.5, $row['accuracy_m']);
        $this->assertSame(42, $row['battery_pct']);
        // effective_end comes from a fresh resolve() at read time, not from
        // whatever was cached when the point was written (see
        // PositionController::lastKnown()'s docblock).
        $this->assertSame($thursday->setTime(16, 0)->utc()->toISOString(), $row['effective_end']);
    }
}
