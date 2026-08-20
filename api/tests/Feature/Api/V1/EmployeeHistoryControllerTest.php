<?php

namespace Tests\Feature\Api\V1;

use App\Models\ShiftTemplate;
use App\Models\User;
use App\Services\TrackingGate;
use App\Services\TrackingSessionManager;
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

    private function point(CarbonImmutable $recordedAt, array $overrides = []): array
    {
        return array_merge([
            'lat' => 23.5,
            'lng' => 58.4,
            'accuracy_m' => 5.0,
            'speed_mps' => 0.0,
            'heading_deg' => 0.0,
            'battery_pct' => 80,
            'is_mocked' => false,
            'recorded_at' => $recordedAt->toISOString(),
        ], $overrides);
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

    public function test_trail_still_shows_recorded_data_after_the_employee_shifts_row_is_later_deleted(): void
    {
        $template = ShiftTemplate::factory()->create(['start_time' => '07:00:00', 'end_time' => '16:00:00']);
        $employee = User::factory()->create();
        $sunday = CarbonImmutable::parse('next Sunday', 'Asia/Muscat')->startOfDay();
        $assignment = $employee->employeeShifts()->create(['template_id' => $template->id, 'effective_from' => $sunday->subMonth()]);

        CarbonImmutable::setTestNow($sunday->setTime(8, 0));
        app(TrackingGate::class)->process($employee, [$this->point($sunday->setTime(8, 0))]);

        $assignment->delete();
        $this->assertSame(0, $employee->employeeShifts()->count());

        $this->actingAs(User::factory()->admin()->create());
        $response = $this->getJson("/api/v1/employees/{$employee->id}/trail?date={$sunday->toDateString()}");

        $response->assertOk();
        $data = $response->json();
        $this->assertSame(1, $data['points_count']);
        $this->assertCount(1, $data['shifts']);
    }

    public function test_two_shifts_the_same_day_are_returned_as_two_separate_grouped_shifts(): void
    {
        $morning = ShiftTemplate::factory()->create(['start_time' => '07:00:00', 'end_time' => '09:00:00']);
        $evening = ShiftTemplate::factory()->create(['start_time' => '13:00:00', 'end_time' => '15:00:00']);
        $employee = User::factory()->create();
        $sunday = CarbonImmutable::parse('next Sunday', 'Asia/Muscat')->startOfDay();
        $employee->employeeShifts()->create([
            'template_id' => $morning->id,
            'effective_from' => $sunday->subMonth()->utc(),
            'effective_to' => $sunday->setTime(10, 0)->utc(),
        ]);
        $employee->employeeShifts()->create([
            'template_id' => $evening->id,
            'effective_from' => $sunday->setTime(10, 0)->utc(),
            'effective_to' => null,
        ]);

        $gate = app(TrackingGate::class);
        $sessions = app(TrackingSessionManager::class);

        CarbonImmutable::setTestNow($sunday->setTime(7, 30));
        $gate->process($employee, [$this->point($sunday->setTime(7, 30))]);

        CarbonImmutable::setTestNow($sunday->setTime(10, 0));
        $sessions->closeEndedSessions(CarbonImmutable::now());

        CarbonImmutable::setTestNow($sunday->setTime(13, 30));
        $gate->process($employee, [$this->point($sunday->setTime(13, 30))]);

        $this->actingAs(User::factory()->admin()->create());
        $response = $this->getJson("/api/v1/employees/{$employee->id}/trail?date={$sunday->toDateString()}");

        $response->assertOk();
        $data = $response->json();
        $this->assertSame(2, $data['points_count']);
        $this->assertCount(2, $data['shifts']);
        $this->assertSame(0, $data['points'][0]['shift_index']);
        $this->assertSame(1, $data['points'][1]['shift_index']);
    }

    public function test_a_day_with_no_recorded_points_returns_an_empty_trail_even_with_an_active_shift(): void
    {
        $template = ShiftTemplate::factory()->create(['start_time' => '07:00:00', 'end_time' => '16:00:00']);
        $employee = User::factory()->create();
        $sunday = CarbonImmutable::parse('next Sunday', 'Asia/Muscat')->startOfDay();
        $employee->employeeShifts()->create(['template_id' => $template->id, 'effective_from' => $sunday->subMonth()]);

        $this->actingAs(User::factory()->admin()->create());
        $response = $this->getJson("/api/v1/employees/{$employee->id}/trail?date={$sunday->toDateString()}");

        $response->assertOk();
        $data = $response->json();
        $this->assertSame(0, $data['distance_m']);
        $this->assertSame([], $data['shifts']);
        $this->assertSame([], $data['points']);
    }
}
