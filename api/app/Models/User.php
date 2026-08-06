<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'phone',
        'email',
        'username',
        'password',
        'team_id',
        'role',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function employeeShifts(): HasMany
    {
        return $this->hasMany(EmployeeShift::class, 'employee_id');
    }

    public function shiftExceptions(): HasMany
    {
        return $this->hasMany(ShiftException::class, 'employee_id');
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class, 'employee_id');
    }

    /**
     * At most one of these ever exists per employee — enforced by the
     * partial unique index on devices(employee_id) WHERE revoked_at IS
     * NULL, not just by this query. See App\Services\DeviceService.
     */
    public function activeDevice(): HasOne
    {
        return $this->hasOne(Device::class, 'employee_id')->whereNull('revoked_at');
    }
}
