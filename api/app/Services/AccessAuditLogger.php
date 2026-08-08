<?php

namespace App\Services;

use App\Enums\AccessAuditAction;
use App\Models\AccessAuditLog;
use App\Models\User;

final class AccessAuditLogger
{
    public function record(User $actor, int $targetEmployeeId, AccessAuditAction $action, ?string $ip): AccessAuditLog
    {
        return AccessAuditLog::create([
            'actor_id' => $actor->id,
            'target_employee_id' => $targetEmployeeId,
            'action' => $action,
            'ip' => $ip,
        ]);
    }
}
