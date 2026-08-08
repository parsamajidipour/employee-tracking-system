<?php

namespace App\Services;

use App\Models\ScheduleChangeLog;
use App\Models\User;
use Carbon\CarbonInterface;

final class ScheduleChangeLogger
{
    /**
     * @param  ?array<string, mixed>  $before  null when the row didn't exist before (create)
     * @param  ?array<string, mixed>  $after  null when the row no longer exists after (delete)
     */
    public function record(
        User $actor,
        int $targetEmployeeId,
        ?array $before,
        ?array $after,
        ?CarbonInterface $effectiveFrom,
        ?string $reason,
    ): ScheduleChangeLog {
        return ScheduleChangeLog::create([
            'actor_id' => $actor->id,
            'target_employee_id' => $targetEmployeeId,
            'before' => $before,
            'after' => $after,
            'effective_from' => $effectiveFrom?->toImmutable()->utc(),
            'reason' => $reason,
        ]);
    }
}
