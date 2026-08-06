<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * `session_id` has no FK yet and is always null in this phase —
 * tracking_sessions and session lifecycle aren't part of this change. Only
 * `App\Services\TrackingGate` writes rows here (CLAUDE.md invariant: a point
 * is persisted only after passing the shift gate) — no other code path
 * should call `create()` on this model.
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
}
