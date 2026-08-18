<?php

namespace Tests\Feature\Api\V1;

use App\Models\ShiftException;
use App\Models\ShiftTemplate;
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

        Redis::connection()->flushdb();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_snapshot_excludes_employees_outside_their_window(): void
    {
        $template = ShiftTemplate::factory()->create();

        $inWindowEmployee = User::factory()->create();
        $onLeaveEmployee = User::factory()->create();

        $sunday = CarbonImmutable::parse('next Sunday', 'Asia/Muscat')->startOfDay();
        $thursday = $sunday->addDays(4);

        foreach ([$inWindowEmployee, $onLeaveEmployee] as $employee) {
            $employee->employeeShifts()->create(['template_id' => $template->id, 'effective_from' => $sunday->subMonth()]);
        }

        ShiftException::factory()->leave()->create([
            'employee_id' => $onLeaveEmployee->id,
            'date' => $thursday->toDateString(),
        ]);

        CarbonImmutable::setTestNow($thursday->setTime(10, 0));

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

    public function test_snapshot_row_includes_name_telemetry_and_effective_end(): void
    {
        $template = ShiftTemplate::factory()->create();

        $employee = User::factory()->create(['name' => 'Fahad']);

        $sunday = CarbonImmutable::parse('next Sunday', 'Asia/Muscat')->startOfDay();
        $thursday = $sunday->addDays(4);
        $employee->employeeShifts()->create(['template_id' => $template->id, 'effective_from' => $sunday->subMonth()]);
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
        $this->assertEquals(12.5, $row['accuracy_m']);
        $this->assertSame(42, $row['battery_pct']);
        $this->assertSame($thursday->setTime(16, 0)->utc()->toISOString(), $row['effective_end']);
    }
}
