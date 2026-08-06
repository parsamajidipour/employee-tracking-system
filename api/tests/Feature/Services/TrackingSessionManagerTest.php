<?php

namespace Tests\Feature\Services;

use App\Enums\TrackingSessionEndReason;
use App\Models\ShiftTemplate;
use App\Models\Team;
use App\Models\TrackingSession;
use App\Models\User;
use App\Services\TrackingSessionManager;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackingSessionManagerTest extends TestCase
{
    use RefreshDatabase;

    private TrackingSessionManager $manager;

    private User $employee;

    private CarbonImmutable $sunday;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = app(TrackingSessionManager::class);

        $team = Team::factory()->create(['timezone' => 'Asia/Muscat']);
        ShiftTemplate::factory()->create(['team_id' => $team->id]); // Sun-Thu 07:00-16:00
        $this->employee = User::factory()->create(['team_id' => $team->id]);

        $this->sunday = CarbonImmutable::parse('next Sunday', 'Asia/Muscat')->startOfDay();
    }

    public function test_the_close_job_is_idempotent(): void
    {
        $thursday = $this->sunday->addDays(4);

        $session = TrackingSession::factory()->create([
            'employee_id' => $this->employee->id,
            'started_at' => $thursday->setTime(9, 0),
        ]);

        // Well after the 07:00-16:00 window (and its zero grace) has ended.
        $now = $thursday->setTime(20, 0);

        $firstRun = $this->manager->closeEndedSessions($now);
        $this->assertSame(1, $firstRun);

        $session->refresh();
        $this->assertNotNull($session->ended_at);
        $this->assertSame(TrackingSessionEndReason::WindowClosed, $session->end_reason);
        $closedAt = $session->ended_at;

        // Running again — including "late", well after the first run —
        // must not touch the already-closed session a second time.
        $secondRun = $this->manager->closeEndedSessions($now->addHours(3));
        $this->assertSame(0, $secondRun);

        $session->refresh();
        $this->assertTrue($closedAt->equalTo($session->ended_at));
    }

    public function test_an_open_session_still_inside_its_window_is_not_closed(): void
    {
        $thursday = $this->sunday->addDays(4);

        TrackingSession::factory()->create([
            'employee_id' => $this->employee->id,
            'started_at' => $thursday->setTime(9, 0),
        ]);

        $closed = $this->manager->closeEndedSessions($thursday->setTime(10, 0));

        $this->assertSame(0, $closed);
        $this->assertNull(TrackingSession::where('employee_id', $this->employee->id)->first()->ended_at);
    }
}
