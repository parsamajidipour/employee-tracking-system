<?php

namespace App\Models;

use App\Enums\TrackingSessionEndReason;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Only App\Services\TrackingSessionManager opens or closes a session — see
 * that class for the "at most one open session per employee" invariant,
 * also enforced by a partial unique index in the migration.
 */
class TrackingSession extends Model
{
    /** @use HasFactory<\Database\Factories\TrackingSessionFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'started_at',
        'ended_at',
        'end_reason',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'end_reason' => TrackingSessionEndReason::class,
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
}
