<?php

namespace App\Models;

use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleChangeLog extends Model
{
    use AppendOnly;

    const UPDATED_AT = null;

    protected $table = 'schedule_change_log';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'actor_id',
        'target_employee_id',
        'before',
        'after',
        'effective_from',
        'reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'effective_from' => 'datetime',
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
