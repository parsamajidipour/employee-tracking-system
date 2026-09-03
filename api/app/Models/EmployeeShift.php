<?php

namespace App\Models;

use Database\Factories\EmployeeShiftFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeShift extends Model
{
    /**
     * @use HasFactory<EmployeeShiftFactory>
     */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_id',
        'template_id',
        'effective_from',
        'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ShiftTemplate::class, 'template_id');
    }
}
