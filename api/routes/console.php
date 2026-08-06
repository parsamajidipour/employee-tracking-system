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
