<?php

namespace Tests\Feature\Services;

use App\Models\LocationPoint;
use App\Models\ShiftTemplate;
use App\Models\Team;
use App\Models\User;
use App\Services\TrackingGate;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

        $this->gate = app(TrackingGate::class);

        $team = Team::factory()->create(['timezone' => 'Asia/Muscat']);
        ShiftTemplate::factory()->create(['team_id' => $team->id]); // Sun-Thu 07:00-16:00
        $this->employee = User::factory()->create(['team_id' => $team->id]);

        $this->sunday = CarbonImmutable::parse('next Sunday', 'Asia/Muscat')->startOfDay();
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
        $recordedAt = $thursday->setTime(9, 0); // inside 07:00-16:00

        // The gate processes it 11 hours later — well after the window
        // closed — but the decision must be based on recorded_at, not on
        // "now" at processing time (CLAUDE.md invariant 2).
        CarbonImmutable::setTestNow($thursday->setTime(20, 0));

        $result = $this->gate->process($this->employee, [$this->point($recordedAt)]);

        $this->assertSame(1, $result->accepted);
        $this->assertSame(0, $result->rejected);
    }

    public function test_point_older_than_48_hours_is_rejected_regardless_of_window(): void
    {
        $thursday = $this->sunday->addDays(4);
        $recordedAt = $thursday->subDays(3)->setTime(10, 0); // 72h old, but inside that day's window

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
}
