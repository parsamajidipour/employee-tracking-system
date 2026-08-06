<?php

namespace App\Console\Commands;

use App\Services\TrackingSessionManager;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class CloseEndedTrackingSessions extends Command
{
    protected $signature = 'tracking:close-ended-sessions';

    protected $description = 'Close open tracking sessions whose shift window has ended';

    public function handle(TrackingSessionManager $manager): int
    {
        $closed = $manager->closeEndedSessions(CarbonImmutable::now());

        $this->info("Closed {$closed} tracking session(s).");

        return self::SUCCESS;
    }
}
