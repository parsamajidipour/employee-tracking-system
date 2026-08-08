<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneLocationHistory extends Command
{
    protected $signature = 'tracking:prune-location-history';

    protected $description = 'Delete location history older than the configured retention period';

    public function handle(): int
    {
        DB::table('location_points')
            ->where('recorded_at', '<', CarbonImmutable::now()->utc()->subDays(config('tracking.retention_days')))
            ->delete();

        return self::SUCCESS;
    }
}
