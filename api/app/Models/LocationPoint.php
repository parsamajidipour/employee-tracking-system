<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationPoint extends Model
{
    protected $fillable = [
        'session_id',
        'employee_id',
        'location',
        'distance_m',
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
        'distance_m' => 'float',
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
