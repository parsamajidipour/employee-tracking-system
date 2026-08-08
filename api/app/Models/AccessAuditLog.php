<?php

namespace App\Models;

use App\Enums\AccessAuditAction;
use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessAuditLog extends Model
{
    use AppendOnly;

    const UPDATED_AT = null;

    protected $table = 'access_audit_log';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'actor_id',
        'target_employee_id',
        'action',
        'ip',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => AccessAuditAction::class,
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function targetEmployee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_employee_id');
    }
}
