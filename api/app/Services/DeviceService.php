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

/**
 * The single place a device gets logged in or revoked — the mobile-app
 * counterpart to App\Services\TrackingGate. "One active device per
 * employee" (the phones are company-issued; a second device means a
 * replacement or something wrong, see DECISIONS.md) is enforced in the
 * database by a partial unique index on devices(employee_id) WHERE
 * revoked_at IS NULL, the same pattern App\Services\TrackingSessionManager
 * uses for "at most one open session per employee": login() creates the
 * device row first and expects to lose a race sometimes, re-reading on
 * conflict rather than checking-then-acting.
 */
final class DeviceService
{
    private const UNIQUE_VIOLATION_SQLSTATE = '23505';

    public function login(string $username, string $password, string $deviceIdentifier, ?string $deviceName): DeviceLoginResult
    {
        $employee = User::query()
            ->where('username', $username)
            ->where('role', UserRole::Employee)
            ->first();

        if ($employee === null || ! Hash::check($password, $employee->password)) {
            return DeviceLoginResult::invalidCredentials();
        }

        if (! $employee->is_active) {
            return DeviceLoginResult::inactive();
        }

        try {
            // Wrapped in its own transaction so a caught unique-violation
            // below only rolls back this savepoint, not the whole
            // request's transaction — Postgres aborts an entire
            // transaction on any error inside it (unlike MySQL), so
            // without this, the conflict path would leave the connection
            // unusable for whatever runs next (visible under
            // RefreshDatabase's per-test wrapping transaction, but exactly
            // as real under any transaction a caller might already be in).
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

        // Minted only after the device row exists, deliberately — a token
        // never exists without a device row backing it, so there's nothing
        // to clean up if the create above had failed instead.
        $token = $employee->createToken($deviceName ?: $deviceIdentifier);
        $device->update(['personal_access_token_id' => $token->accessToken->id]);

        return DeviceLoginResult::success($token->plainTextToken);
    }

    /**
     * Deletes the token outright — Sanctum's guard then fails auth for it
     * on the very next request, which is what makes a revoked device's
     * token 401 on /track rather than needing its own revocation check
     * anywhere else. The device row itself is kept, marked revoked, for
     * history (see App\Models\Device).
     */
    public function revoke(Device $device): void
    {
        if ($device->personal_access_token_id !== null) {
            PersonalAccessToken::where('id', $device->personal_access_token_id)->delete();
        }

        $device->update(['revoked_at' => now()]);
    }
}
