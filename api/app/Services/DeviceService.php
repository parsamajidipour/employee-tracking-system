<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Device;
use App\Models\User;
use App\ValueObjects\DeviceLoginResult;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

final class DeviceService
{
    private const UNIQUE_VIOLATION_SQLSTATE = '23505';

    public function login(string $identifier, string $password, string $deviceIdentifier, ?string $deviceName): DeviceLoginResult
    {
        $employee = User::query()
            ->where('role', UserRole::Employee)
            ->where(fn ($query) => $query->where('email', $identifier)->orWhere('phone', $identifier))
            ->first();

        if ($employee === null || ! Hash::check($password, $employee->password)) {
            return DeviceLoginResult::invalidCredentials();
        }

        if (! $employee->is_active) {
            return DeviceLoginResult::inactive();
        }

        try {
            $device = DB::transaction(fn () => Device::create([
                'employee_id' => $employee->id,
                'device_identifier' => $deviceIdentifier,
                'device_name' => $deviceName,
            ]));
        } catch (QueryException $e) {
            if (($e->errorInfo[0] ?? null) !== self::UNIQUE_VIOLATION_SQLSTATE) {
                throw $e;
            }

            return DeviceLoginResult::deviceConflict();
        }

        $token = $employee->createToken($deviceName ?: $deviceIdentifier);
        $device->update(['personal_access_token_id' => $token->accessToken->id]);

        return DeviceLoginResult::success($token->plainTextToken);
    }

    public function revoke(Device $device): void
    {
        if ($device->personal_access_token_id !== null) {
            PersonalAccessToken::where('id', $device->personal_access_token_id)->delete();
        }

        $device->update(['revoked_at' => now()]);
    }
}
