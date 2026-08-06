<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftTemplate extends Model
{
    /** @use HasFactory<\Database\Factories\ShiftTemplateFactory> */
    use HasFactory;

    protected $fillable = [
        'team_id',
        'name',
        'timezone',
        'days_of_week',
        'start_time',
        'end_time',
        'grace_before_min',
        'grace_after_min',
        'max_daily_minutes',
    ];

    protected function casts(): array
    {
        return [
            'days_of_week' => 'array',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
