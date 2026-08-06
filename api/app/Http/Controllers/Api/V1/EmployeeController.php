<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeRequest;
use App\Models\Team;
use App\Models\TrackingSession;
use App\Models\User;
use App\Services\DeviceService;
use App\Services\ShiftWindowResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EmployeeController extends Controller
{
    /**
     * Every account, not just role=employee ones — hr/admin manage the
     * whole roster here, including their own kind. Only reachable behind
     * `capability:manage-schedules` (see routes/api.php): this response
     * carries phone numbers, usernames, active status, and a device
     * identifier, none of which a supervisor's view-locations capability
     * is meant to see.
     */
    public function index(): JsonResponse
    {
        $employees = User::query()
            ->with('activeDevice')
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'email', 'username', 'role', 'is_active']);

        return response()->json($employees->map(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'phone' => $user->phone,
            'email' => $user->email,
            'username' => $user->username,
            'role' => $user->role,
            'is_active' => $user->is_active,
            'device' => $user->activeDevice === null ? null : [
                'device_identifier' => $user->activeDevice->device_identifier,
                'device_name' => $user->activeDevice->device_name,
                'last_seen_at' => $user->activeDevice->last_seen_at?->toISOString(),
            ],
        ]));
    }

    /**
     * Always role=employee, always the one default team — see
     * StoreEmployeeRequest's docblock and DECISIONS.md's "multi-team is
     * deferred" entry. Queried fresh rather than cached: this deployment
     * only ever has the one
     * team the seed_default_team_and_backfill_users migration created,
     * and re-querying it is cheap at this project's scale.
     */
    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $employee = User::create([
            'name' => $request->validated('name'),
            'phone' => $request->validated('phone'),
            'username' => $request->validated('username'),
            'password' => $request->validated('password'),
            'role' => UserRole::Employee,
            'is_active' => $request->boolean('is_active', true),
            'team_id' => Team::query()->orderBy('id')->value('id'),
        ]);

        return response()->json($employee, 201);
    }

    public function setActive(Request $request, User $employee): JsonResponse
    {
        $validated = $request->validate(['is_active' => ['required', 'boolean']]);

        $employee->update(['is_active' => $validated['is_active']]);

        return response()->json($employee);
    }

    public function resetPassword(Request $request, User $employee): Response
    {
        $validated = $request->validate(['password' => ['required', 'string', 'min:8']]);

        $employee->update(['password' => $validated['password']]);

        return response()->noContent();
    }

    /**
     * Deletes the employee's active device's token and marks the device
     * row revoked (App\Services\DeviceService::revoke()) — a no-op, not an
     * error, if there isn't one. "Revoke first, then log in on the new
     * phone": there's nothing else to confirm here, since login() already
     * refuses a second device on its own.
     */
    public function revokeDevice(User $employee, DeviceService $devices): Response
    {
        if ($employee->activeDevice !== null) {
            $devices->revoke($employee->activeDevice);
        }

        return response()->noContent();
    }

    /**
     * The admin-facing counterpart to GET /api/v1/me/window: same resolver,
     * anchored on a date the admin picked instead of "now" — so the panel's
     * per-employee schedule page shows what the resolver actually produces,
     * not a second implementation of window logic (CLAUDE.md invariant 8).
     */
    public function window(Request $request, User $employee, ShiftWindowResolver $resolver): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $window = $resolver->resolveForDate($employee, $validated['date']);

        return response()->json([
            'date' => $validated['date'],
            'window' => $window?->toApiArray(),
        ]);
    }

    /**
     * The live map's detail panel's "session start" — the employee's
     * currently open tracking_sessions row, if any. Deliberately its own
     * endpoint rather than folded into window() or the positions snapshot:
     * a session starts once and doesn't change on every point the way a
     * position does, so it has no reason to ride along on the high-frequency
     * payload — fetched only when a supervisor actually opens the panel.
     */
    public function session(User $employee): JsonResponse
    {
        $session = TrackingSession::where('employee_id', $employee->id)->whereNull('ended_at')->first();

        return response()->json([
            'started_at' => $session?->started_at?->toISOString(),
        ]);
    }
}
