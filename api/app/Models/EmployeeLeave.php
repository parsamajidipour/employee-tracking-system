<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeLeave extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'employee_id',
        'starts_at',
        'ends_at',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  Builder<EmployeeLeave>  $query
     */
    public function scopeOverlapping(Builder $query, mixed $start, mixed $end): void
    {
        $query->where('starts_at', '<', $end)->where('ends_at', '>', $start);
    }
}
