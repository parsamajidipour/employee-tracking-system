<?php

namespace App\Models;

use App\Enums\CasePriority;
use App\Enums\CaseStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InspectionCase extends Model
{
    protected $table = 'inspection_cases';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'reference_no',
        'title',
        'property_address',
        'location',
        'status',
        'priority',
        'assigned_to',
        'created_by',
        'assigned_at',
        'accepted_at',
        'planned_at',
        'started_at',
        'completed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => CaseStatus::class,
            'priority' => CasePriority::class,
            'assigned_at' => 'datetime',
            'accepted_at' => 'datetime',
            'planned_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<InspectionCase>  $query
     */
    public function scopeWithLatLng(Builder $query): void
    {
        $query->selectRaw('inspection_cases.*, ST_Y(location::geometry) AS lat, ST_X(location::geometry) AS lng');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<CaseStatusEvent, $this>
     */
    public function statusEvents(): HasMany
    {
        return $this->hasMany(CaseStatusEvent::class, 'inspection_case_id')->orderBy('created_at');
    }

    /**
     * @return HasMany<CasePhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(CasePhoto::class, 'inspection_case_id')->orderBy('captured_at');
    }
}
