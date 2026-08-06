<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
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
}
