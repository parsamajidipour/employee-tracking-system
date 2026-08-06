<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Idempotent (CREATE TABLE IF NOT EXISTS internally) — daily is cheap and
// means a missed run or restart never leaves partitions missing for long.
Schedule::command('tracking:ensure-partitions')->daily();

// Idempotent (only ever selects ended_at IS NULL rows) and safe to run late
// (recomputes each session's actual end from the resolver, not from "now") —
// see App\Services\TrackingSessionManager::closeEndedSessions(). Every five
// minutes is frequent enough that an open session is closed soon after its
// window ends without being so frequent it matters at this project's scale.
Schedule::command('tracking:close-ended-sessions')->everyFiveMinutes();
