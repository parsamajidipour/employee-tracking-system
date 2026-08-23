<?php

namespace App\Models;

use App\Enums\CaseStatus;
use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseStatusEvent extends Model
{
    use AppendOnly;

    const UPDATED_AT = null;

    protected $table = 'case_status_events';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'inspection_case_id',
        'actor_id',
        'from_status',
        'to_status',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => CaseStatus::class,
            'to_status' => CaseStatus::class,
            'created_at' => 'datetime',
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
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
