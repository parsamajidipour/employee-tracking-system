<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Only `App\Services\TrackingGate` writes rows here (CLAUDE.md invariant: a
 * point is persisted only after passing the shift gate) — no other code
 * path should call `create()` on this model. `session_id` is always set by
 * the time a row is written; see App\Services\TrackingSessionManager for how
 * the session it points to is opened or reused.
 */
class LocationPoint extends Model
{
    protected $fillable = [
        'session_id',
        'employee_id',
        'location',
        'accuracy_m',
        'speed_mps',
        'heading_deg',
        'battery_pct',
        'is_mocked',
        'recorded_at',
        'received_at',
    ];

    protected $casts = [
        'is_mocked' => 'boolean',
        'recorded_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TrackingSession::class, 'session_id');
    }
}
