<?php

use App\Http\Controllers\Api\V1\EmployeeController;
use App\Http\Controllers\Api\V1\EmployeeShiftController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\PositionController;
use App\Http\Controllers\Api\V1\ShiftExceptionController;
use App\Http\Controllers\Api\V1\ShiftTemplateController;
use App\Http\Controllers\Api\V1\TeamController;
use App\Http\Controllers\Api\V1\TrackController;
use App\Http\Controllers\BasemapController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

// Self-hosted basemap tiles for the live map (see DECISIONS.md) — no auth,
// no capability gate. It's OSM basemap geometry, not employee location
// data; nothing in this response depends on who's asking.
Route::get('/basemap/oman.pmtiles', [BasemapController::class, 'oman']);

// Session-cookie login for the Sanctum SPA flow (Nuxt panel/). Capability
// authorization (App\Enums\Capability, the `capability` middleware) is
// enforced on the routes below, not here — this only authenticates.
Route::post('/login', function (Request $request) {
    $credentials = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required'],
    ]);

    if (! Auth::attempt($credentials)) {
        throw ValidationException::withMessages([
            'email' => ['These credentials do not match our records.'],
        ]);
    }

    $request->session()->regenerate();

    return response()->json(['user' => Auth::user()]);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', function (Request $request) {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    });

    // Test endpoint proving an authenticated cross-origin request from the
    // Nuxt panel reaches api/ end to end.
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});

// Mobile. SPEC section 5 calls for a device-bound token — Sanctum personal
// access tokens (already migrated) serve that role: one token per device,
// checked via the same auth:sanctum guard used above. Token issuance/pairing
// is a future auth change, not part of this one.
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::post('/track', [TrackController::class, 'store']);
    Route::get('/me/window', [MeController::class, 'window']);

    // Panel-facing team/schedule management. Same auth:sanctum guard as
    // above (Sanctum's SPA session cookie, in practice, for this client),
    // gated further by capability via the `capability` middleware
    // (App\Http\Middleware\EnsureCapability) — see that class's docblock
    // for why a mobile token is rejected on every route in this group
    // regardless of capability. Gated on capability, not role rank: hr
    // (manage-schedules) and supervisor (view-locations) are separate
    // concerns, not tiers of one hierarchy — see App\Enums\UserRole.
    Route::middleware('capability:view-locations')->group(function () {
        Route::get('/employees', [EmployeeController::class, 'index']);
        Route::get('/employees/{employee}/window', [EmployeeController::class, 'window']);
        Route::get('/employees/{employee}/session', [EmployeeController::class, 'session']);
        Route::get('/positions', [PositionController::class, 'index']);
    });

    Route::middleware('capability:manage-schedules')->group(function () {
        Route::get('/teams', [TeamController::class, 'index']);
        Route::post('/teams', [TeamController::class, 'store']);
        Route::put('/teams/{team}', [TeamController::class, 'update']);
        Route::delete('/teams/{team}', [TeamController::class, 'destroy']);

        Route::get('/shift-templates', [ShiftTemplateController::class, 'index']);
        Route::post('/shift-templates', [ShiftTemplateController::class, 'store']);
        Route::put('/shift-templates/{shiftTemplate}', [ShiftTemplateController::class, 'update']);
        Route::delete('/shift-templates/{shiftTemplate}', [ShiftTemplateController::class, 'destroy']);

        // Every store/update/destroy here writes a schedule_change_log row
        // (App\Services\ScheduleChangeLogger) — see EmployeeShiftController.
        Route::get('/employee-shifts', [EmployeeShiftController::class, 'index']);
        Route::post('/employee-shifts', [EmployeeShiftController::class, 'store']);
        Route::put('/employee-shifts/{employeeShift}', [EmployeeShiftController::class, 'update']);
        Route::delete('/employee-shifts/{employeeShift}', [EmployeeShiftController::class, 'destroy']);

        Route::get('/shift-exceptions', [ShiftExceptionController::class, 'index']);
        Route::post('/shift-exceptions', [ShiftExceptionController::class, 'store']);
        Route::put('/shift-exceptions/{shiftException}', [ShiftExceptionController::class, 'update']);
        Route::delete('/shift-exceptions/{shiftException}', [ShiftExceptionController::class, 'destroy']);
    });
});
