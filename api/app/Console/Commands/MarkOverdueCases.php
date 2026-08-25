<?php

namespace App\Console\Commands;

use App\Enums\CaseStatus;
use App\Models\InspectionCase;
use App\Services\CaseLifecycleService;
use Illuminate\Console\Command;

class MarkOverdueCases extends Command
{
    protected $signature = 'cases:mark-overdue';

    protected $description = 'Move scheduled inspection cases past their planned time to overdue';

    public function handle(CaseLifecycleService $lifecycle): int
    {
        $count = 0;

        InspectionCase::query()
            ->where('status', CaseStatus::Accepted)
            ->whereNotNull('planned_at')
            ->where('planned_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($cases) use ($lifecycle, &$count) {
                foreach ($cases as $case) {
                    $lifecycle->markOverdue($case);
                    $count++;
                }
            });

        $this->info("Marked {$count} case(s) overdue.");

        return self::SUCCESS;
    }
}
