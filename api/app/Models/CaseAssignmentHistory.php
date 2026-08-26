<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseAssignmentHistory extends Model
{
    public $timestamps = false;

    protected $table = 'case_assignment_histories';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'inspection_case_id',
        'employee_id',
        'actor_id',
        'assigned_at',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<InspectionCase, $this>
     */
    public function case(): BelongsTo
    {
        return $this->belongsTo(InspectionCase::class, 'inspection_case_id');
    }
}
