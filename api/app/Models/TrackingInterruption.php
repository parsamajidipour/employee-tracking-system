<?php

namespace App\Models;

use App\Enums\InterruptionReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingInterruption extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'reason',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'reason' => InterruptionReason::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
}
