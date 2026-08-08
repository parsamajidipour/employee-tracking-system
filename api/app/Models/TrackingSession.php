<?php

namespace App\Models;

use App\Enums\TrackingSessionEndReason;
use Database\Factories\TrackingSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingSession extends Model
{
    /**
     * @use HasFactory<TrackingSessionFactory>
     */
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
