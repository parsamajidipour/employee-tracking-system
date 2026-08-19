<?php

namespace Tests\Feature\Api\V1;

use App\Models\ShiftTemplate;
use App\Models\User;
use App\Services\TrackingGate;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeHistoryControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_trail_decimates_closely_spaced_points_but_keeps_the_full_count_in_the_summary(): void
    {
        $template = ShiftTemplate::factory()->create(['start_time' => '07:00:00', 'end_time' => '16:00:00']);
        $employee = User::factory()->create();

        $sunday = CarbonImmutable::parse('next Sunday', 'Asia/Muscat')->startOfDay();
        $employee->employeeShifts()->create(['template_id' => $template->id, 'effective_from' => $sunday->subMonth()]);

        $gate = app(TrackingGate::class);
        $start = $sunday->setTime(8, 0);

        // 60 points, ~1m apart — well under the 12m decimation threshold — plus
        // a final point 500m away so the route has real extent.
        $batch = [];
        for ($i = 0; $i < 60; $i++) {
            $batch[] = [
                'lat' => 23.5 + $i * 0.00001,
                'lng' => 58.4,
                'accuracy_m' => 5.0,
                'speed_mps' => 0.0,
                'heading_deg' => 0.0,
                'battery_pct' => 80,
                'is_mocked' => false,
                'recorded_at' => $start->addSeconds($i * 10)->toISOString(),
            ];
        }
        $batch[] = [
            'lat' => 23.51,
            'lng' => 58.41,
            'accuracy_m' => 5.0,
            'speed_mps' => 1.0,
            'heading_deg' => 0.0,
            'battery_pct' => 79,
            'is_mocked' => false,
            'recorded_at' => $start->addSeconds(600)->toISOString(),
        ];

        CarbonImmutable::setTestNow($start);
        $gate->process($employee, $batch);

        $this->actingAs(User::factory()->admin()->create());

        $response = $this->getJson("/api/v1/employees/{$employee->id}/trail?date={$sunday->toDateString()}");

        $response->assertOk();
        $data = $response->json();

        $this->assertSame(61, $data['points_count']);
        $this->assertLessThan(61, count($data['points']));
        $this->assertGreaterThanOrEqual(2, count($data['points']));

        $firstReturned = CarbonImmutable::parse($data['points'][0]['recorded_at']);
        $lastReturned = CarbonImmutable::parse($data['points'][count($data['points']) - 1]['recorded_at']);
        $this->assertTrue($firstReturned->equalTo($start));
        $this->assertTrue($lastReturned->equalTo($start->addSeconds(600)));
    }
}
