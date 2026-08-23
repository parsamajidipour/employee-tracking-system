<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CasePhoto extends Model
{
    protected $table = 'case_photos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'inspection_case_id',
        'employee_id',
        'disk_path',
        'location',
        'accuracy_m',
        'distance_from_case_m',
        'is_gps_verified',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'is_gps_verified' => 'boolean',
            'distance_from_case_m' => 'float',
            'accuracy_m' => 'float',
            'captured_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<CasePhoto>  $query
     */
    public function scopeWithLatLng(Builder $query): void
    {
        $query->selectRaw('case_photos.*, ST_Y(location::geometry) AS lat, ST_X(location::geometry) AS lng');
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
