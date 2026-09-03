<?php

namespace App\Models;

use Database\Factories\ShiftTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShiftTemplate extends Model
{
    /**
     * @use HasFactory<ShiftTemplateFactory>
     */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
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
            'grace_before_min' => 'integer',
            'grace_after_min' => 'integer',
            'max_daily_minutes' => 'integer',
        ];
    }

    /**
     * @return HasMany<EmployeeShift, $this>
     */
    public function employeeShifts(): HasMany
    {
        return $this->hasMany(EmployeeShift::class, 'template_id');
    }
}
