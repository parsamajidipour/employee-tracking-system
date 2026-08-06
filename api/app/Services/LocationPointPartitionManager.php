<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Creates the monthly range partitions `location_points` needs, so they never
 * have to be created by hand. Used both by the migration that creates the
 * parent table (to bootstrap the partitions the system needs on day one) and
 * by the `tracking:ensure-partitions` scheduled command (to keep creating
 * them going forward). `CREATE TABLE IF NOT EXISTS` makes every call
 * idempotent — safe to run daily, safe to run after downtime of any length.
 */
final class LocationPointPartitionManager
{
    public const DEFAULT_LOOKAHEAD_MONTHS = 3;

    /**
     * Ensures a monthly partition exists for the calendar month of $from and
     * for each of the following ($months - 1) months.
     */
    public function ensure(CarbonInterface $from, int $months = self::DEFAULT_LOOKAHEAD_MONTHS): void
    {
        $start = CarbonImmutable::instance($from)->utc()->startOfMonth();

        for ($i = 0; $i < $months; $i++) {
            $monthStart = $start->addMonths($i);
            $monthEnd = $monthStart->addMonth();

            // $monthStart/$monthEnd are computed internally, never from user
            // input, so inlining the literal dates below carries no
            // injection risk — Postgres partition bounds in a DDL statement
            // can't be parameterized the way a normal query's WHERE can.
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
