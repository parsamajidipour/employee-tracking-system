<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A mobile device an employee has logged into via App\Services\DeviceService.
 * Rows are never deleted — a revoke sets `revoked_at` and stays, so an
 * employee's device history (replacements, revocations) is visible in the
 * panel rather than disappearing. "At most one active device per employee"
 * is a database invariant (see the create_devices_table migration), not
 * just an application check.
 */
class Device extends Model
{
    protected $fillable = [
        'employee_id',
        'device_identifier',
        'device_name',
        'personal_access_token_id',
        'last_seen_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
}
