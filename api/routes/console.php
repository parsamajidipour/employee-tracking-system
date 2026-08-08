<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('tracking:ensure-partitions')->daily();

Schedule::command('tracking:close-ended-sessions')->everyFiveMinutes();

Schedule::command('tracking:prune-location-history')->daily();
