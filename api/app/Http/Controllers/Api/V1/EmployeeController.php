<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TrackingSession;
use App\Models\User;
use App\Services\ShiftWindowResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            User::query()->with('team')->orderBy('name')->get(['id', 'name', 'email', 'team_id'])
        );
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
