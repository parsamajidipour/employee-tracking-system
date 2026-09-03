<?php

namespace App\Models;

use App\Enums\ShiftExceptionType;
use Database\Factories\ShiftExceptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShiftException extends Model
{
    /**
     * @use HasFactory<ShiftExceptionFactory>
     */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_id',
        'date',
        'type',
        'start_at',
        'end_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'type' => ShiftExceptionType::class,
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
}
