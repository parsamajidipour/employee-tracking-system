<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;

final class CaseNotificationService
{
    /**
     * @param  list<int>|null  $employeeIds
     * @param  list<string>|null  $types
     */
    public function delete(int $caseId, ?array $employeeIds = null, ?array $types = null): void
    {
        DatabaseNotification::query()
            ->where('notifiable_type', User::class)
            ->whereRaw("data::jsonb ->> 'case_id' = ?", [(string) $caseId])
            ->when($employeeIds !== null, fn ($query) => $query->whereIn('notifiable_id', $employeeIds))
            ->when($types !== null, fn ($query) => $query->whereIn(DB::raw("data::jsonb ->> 'type'"), $types))
            ->delete();
    }
}
