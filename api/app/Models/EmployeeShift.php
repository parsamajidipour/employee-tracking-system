<?php

namespace App\Models;

use Database\Factories\EmployeeShiftFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeShift extends Model
{
    /**
     * @use HasFactory<EmployeeShiftFactory>
     */
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'template_id',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ShiftTemplate::class, 'template_id');
    }
}
