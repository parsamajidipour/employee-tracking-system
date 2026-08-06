<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only (CLAUDE.md). update()/delete() are overridden to throw rather
 * than silently succeed — there must be no code path that mutates or
 * removes a row here, not even one nobody's written yet.
 */
class ScheduleChangeLog extends Model
{
    const UPDATED_AT = null;

    // Table name per SPEC section 4 is singular ("schedule_change_log"),
    // not Eloquent's default pluralization.
    protected $table = 'schedule_change_log';

    protected $fillable = [
        'actor_id',
        'target_employee_id',
        'before',
        'after',
        'effective_from',
        'reason',
    ];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
        'effective_from' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function targetEmployee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_employee_id');
    }

    public function update(array $attributes = [], array $options = []): bool
    {
        throw new LogicException('schedule_change_log is append-only: no update path exists.');
    }

    public function delete(): bool
    {
        throw new LogicException('schedule_change_log is append-only: no delete path exists.');
    }
}
