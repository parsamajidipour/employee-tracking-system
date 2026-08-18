<?php

namespace Tests\Feature\Services;

use App\Enums\TrackingSessionEndReason;
use App\Models\EmployeeShift;
use App\Models\ShiftTemplate;
use App\Models\TrackingSession;
use App\Models\User;
use App\Services\TrackingSessionManager;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_the_close_job_is_idempotent(): void
    {
        $thursday = $this->sunday->addDays(4);

        $session = TrackingSession::factory()->create([
            'employee_id' => $this->employee->id,
            'started_at' => $thursday->setTime(9, 0),
        ]);

        $now = $thursday->setTime(20, 0);

        $firstRun = $this->manager->closeEndedSessions($now);
        $this->assertSame(1, $firstRun);

        $session->refresh();
        $this->assertNotNull($session->ended_at);
        $this->assertSame(TrackingSessionEndReason::WindowClosed, $session->end_reason);
        $closedAt = $session->ended_at;

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

    public function test_two_batches_racing_to_open_a_session_reread_and_reuse_the_winner(): void
    {
        $startedAt = $this->sunday->addDays(4)->setTime(9, 0);
        $employeeId = $this->employee->id;
        $winnerId = null;
        $fired = false;

        DB::listen(function ($query) use (&$fired, &$winnerId, $employeeId, $startedAt) {
            if ($fired) {
                return;
            }
            $sql = strtolower($query->sql);
            if (! str_contains($sql, 'tracking_sessions') || ! str_starts_with(trim($sql), 'select')) {
                return;
            }

            $fired = true;
            $winnerId = DB::table('tracking_sessions')->insertGetId([
                'employee_id' => $employeeId,
                'started_at' => $startedAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $result = $this->manager->openOrReuse($this->employee, $startedAt);

        $this->assertNotNull($winnerId);
        $this->assertSame($winnerId, $result->id);
        $this->assertSame(
            1,
            TrackingSession::where('employee_id', $employeeId)->whereNull('ended_at')->count(),
        );
    }
}
