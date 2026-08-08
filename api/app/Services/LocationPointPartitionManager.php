<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final class LocationPointPartitionManager
{
    public const DEFAULT_LOOKAHEAD_MONTHS = 3;

    public function ensure(CarbonInterface $from, int $months = self::DEFAULT_LOOKAHEAD_MONTHS): void
    {
        $start = CarbonImmutable::instance($from)->utc()->startOfMonth();

        for ($i = 0; $i < $months; $i++) {
            $monthStart = $start->addMonths($i);
            $monthEnd = $monthStart->addMonth();

            DB::statement(sprintf(
                'CREATE TABLE IF NOT EXISTS "%s" PARTITION OF location_points FOR VALUES FROM (%s) TO (%s)',
                $this->partitionName($monthStart),
                $this->dateLiteral($monthStart),
                $this->dateLiteral($monthEnd),
            ));
        }
    }

    private function partitionName(CarbonImmutable $monthStart): string
    {
        return 'location_points_'.$monthStart->format('Y_m');
    }

    private function dateLiteral(CarbonImmutable $date): string
    {
        return "'".$date->toDateString()."'";
    }
}
