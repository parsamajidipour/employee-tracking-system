<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseOffer extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'inspection_case_id',
        'employee_id',
        'offered_by',
        'offered_at',
    ];

    protected function casts(): array
    {
        return [
            'offered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<InspectionCase, $this>
     */
    public function case(): BelongsTo
    {
        return $this->belongsTo(InspectionCase::class, 'inspection_case_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
}
