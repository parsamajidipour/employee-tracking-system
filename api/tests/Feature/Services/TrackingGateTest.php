<?php

namespace Tests\Feature\Services;

use App\Events\EmployeePositionUpdated;
use App\Models\EmployeeShift;
use App\Models\LocationPoint;
use App\Models\ShiftTemplate;
use App\Models\TrackingSession;
use App\Models\User;
use App\Services\TrackingGate;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class TrackingGateTest extends TestCase
{
    use RefreshDatabase;

    private TrackingGate $gate;

    private User $employee;

    private CarbonImmutable $sunday;

    protected function setUp(): void
    {
        parent::setUp();

        Redis::connection()->flushdb();

        $this->gate = app(TrackingGate::class);
        $this->sunday = CarbonImmutable::parse('next Sunday', 'Asia/Muscat')->startOfDay();

        $template = ShiftTemplate::factory()->create();
        $this->employee = User::factory()->create();
        EmployeeShift::factory()->create([
            'employee_id' => $this->employee->id,
            'template_id' => $template->id,
            'effective_from' => $this->sunday->subMonth()->utc(),
            'effective_to' => null,
        ]);
    }

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

    public function test_batch_mixing_in_window_and_out_of_window_points_stores_only_in_window_ones(): void
    {
        $thursday = $this->sunday->addDays(4);
        CarbonImmutable::setTestNow($thursday->setTime(19, 0));

        $inWindow = $thursday->setTime(10, 0);
        $outOfWindow = $thursday->setTime(18, 0);

        $result = $this->gate->process($this->employee, [
            $this->point($inWindow),
            $this->point($outOfWindow),
        ]);

        $this->assertSame(1, $result->accepted);
        $this->assertSame(1, $result->rejected);
        $this->assertSame(1, LocationPoint::where('employee_id', $this->employee->id)->count());
    }

    public function test_point_recorded_in_window_but_received_hours_later_is_still_accepted(): void
    {
        $thursday = $this->sunday->addDays(4);
        $recordedAt = $thursday->setTime(9, 0);

        CarbonImmutable::setTestNow($thursday->setTime(20, 0));

        $result = $this->gate->process($this->employee, [$this->point($recordedAt)]);

        $this->assertSame(1, $result->accepted);
        $this->assertSame(0, $result->rejected);
    }

    public function test_point_older_than_48_hours_is_rejected_regardless_of_window(): void
    {
        $thursday = $this->sunday->addDays(4);
        $recordedAt = $thursday->subDays(3)->setTime(10, 0);

        CarbonImmutable::setTestNow($thursday->setTime(10, 0));

        $result = $this->gate->process($this->employee, [$this->point($recordedAt)]);

        $this->assertSame(0, $result->accepted);
        $this->assertSame(1, $result->rejected);
        $this->assertSame(0, LocationPoint::where('employee_id', $this->employee->id)->count());
    }

    public function test_accepted_point_routes_into_the_correct_monthly_partition(): void
    {
        $thursday = $this->sunday->addDays(4);
        $recordedAt = $thursday->setTime(10, 0);
        CarbonImmutable::setTestNow($recordedAt);

        $this->gate->process($this->employee, [$this->point($recordedAt)]);

        $point = LocationPoint::where('employee_id', $this->employee->id)->first();
        $this->assertNotNull($point);

        $partition = DB::selectOne('SELECT tableoid::regclass::text AS partition FROM location_points WHERE id = ?', [$point->id]);

        $this->assertSame('location_points_'.$recordedAt->format('Y_m'), $partition->partition);
    }

    public function test_a_session_opens_once_and_is_not_reopened_by_later_points_in_the_same_window(): void
    {
        $thursday = $this->sunday->addDays(4);
        CarbonImmutable::setTestNow($thursday->setTime(10, 0));

        $this->gate->process($this->employee, [$this->point($thursday->setTime(8, 0))]);
        $this->gate->process($this->employee, [$this->point($thursday->setTime(9, 0))]);

        $this->assertSame(1, TrackingSession::where('employee_id', $this->employee->id)->count());

        $session = TrackingSession::where('employee_id', $this->employee->id)->first();
        $this->assertNull($session->ended_at);
        $this->assertSame(
            2,
            LocationPoint::where('employee_id', $this->employee->id)->where('session_id', $session->id)->count(),
        );
    }

    public function test_a_batch_with_an_older_point_after_a_newer_one_leaves_last_known_at_the_newer_point(): void
    {
        $thursday = $this->sunday->addDays(4);
        CarbonImmutable::setTestNow($thursday->setTime(10, 0));

        $newer = $thursday->setTime(9, 30);
        $older = $thursday->setTime(9, 0);

        $this->gate->process($this->employee, [
            $this->point($newer, ['lat' => 24.0, 'lng' => 59.0]),
            $this->point($older, ['lat' => 20.0, 'lng' => 50.0]),
        ]);

        $lastKnown = json_decode(Redis::get("last_known:{$this->employee->id}"), true);

        $this->assertEquals(24.0, $lastKnown['lat']);
        $this->assertEquals(59.0, $lastKnown['lng']);
        $this->assertTrue(CarbonImmutable::parse($lastKnown['recorded_at'])->equalTo($newer));
    }

    public function test_an_older_batch_delivered_after_a_newer_one_does_not_move_last_known_backwards(): void
    {
        $thursday = $this->sunday->addDays(4);
        CarbonImmutable::setTestNow($thursday->setTime(10, 0));

        $newer = $thursday->setTime(9, 30);
        $older = $thursday->setTime(9, 0);

        $this->gate->process($this->employee, [$this->point($newer, ['lat' => 24.0, 'lng' => 59.0])]);
        $this->gate->process($this->employee, [$this->point($older, ['lat' => 20.0, 'lng' => 50.0])]);

        $lastKnown = json_decode(Redis::get("last_known:{$this->employee->id}"), true);

        $this->assertEquals(24.0, $lastKnown['lat']);
        $this->assertTrue(CarbonImmutable::parse($lastKnown['recorded_at'])->equalTo($newer));
    }

    public function test_a_batch_of_n_accepted_points_produces_exactly_one_broadcast(): void
    {
        Event::fake([EmployeePositionUpdated::class]);

        $thursday = $this->sunday->addDays(4);
        CarbonImmutable::setTestNow($thursday->setTime(10, 0));

        $points = [];
        for ($minute = 0; $minute < 5; $minute++) {
            $points[] = $this->point($thursday->setTime(9, $minute));
        }
        $newestRecordedAt = $thursday->setTime(9, 4);

        $result = $this->gate->process($this->employee, $points);

        $this->assertSame(5, $result->accepted);
        Event::assertDispatchedTimes(EmployeePositionUpdated::class, 1);
        Event::assertDispatched(
            fn (EmployeePositionUpdated $event) => CarbonImmutable::parse($event->recordedAt)->equalTo($newestRecordedAt)
                && $event->effectiveEnd === $thursday->setTime(16, 0)->utc()->toISOString(),
        );
    }
}
