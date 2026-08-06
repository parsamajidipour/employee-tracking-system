<?php

namespace App\Console\Commands;

use App\Services\LocationPointPartitionManager;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class EnsureLocationPointPartitions extends Command
{
    protected $signature = 'tracking:ensure-partitions';

    protected $description = 'Create upcoming monthly location_points partitions so they never have to be created by hand';

    public function handle(LocationPointPartitionManager $manager): int
    {
        $manager->ensure(CarbonImmutable::now());

        $this->info('location_points partitions ensured through '.
            CarbonImmutable::now()->addMonths(LocationPointPartitionManager::DEFAULT_LOOKAHEAD_MONTHS - 1)->format('Y-m').'.');

        return self::SUCCESS;
    }
}
