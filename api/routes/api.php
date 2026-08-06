<?php

use App\Http\Controllers\Api\V1\DeviceAuthController;
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

    // is_active rides along in the credentials array Auth::attempt() passes
    // to the Eloquent user provider, which where()s on every key in it —
    // an inactive account simply isn't found, the same "deactivate means
    // deactivated everywhere, not just the mobile app" rule the device
    // login endpoint enforces on its own path. See EmployeeController's
    // setActive().
    if (! Auth::attempt([...$credentials, 'is_active' => true])) {
        throw ValidationException::withMessages([
            'email' => ['These credentials do not match our records.'],
        ]);
    }

    $request->session()->regenerate();

    return response()->json(['user' => Auth::user()]);
});

// Mobile device login — see App\Services\DeviceService and DECISIONS.md's
// "one active device per employee" entry. Unauthenticated by definition
// (this is how a device gets its token) and rate-limited: it's the one
// endpoint in this app that accepts a password over and over from an
// otherwise-anonymous caller.
Route::post('/v1/device/login', [DeviceAuthController::class, 'login'])->middleware('throttle:5,1');

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
        Route::get('/employees/{employee}/window', [EmployeeController::class, 'window']);
        Route::get('/employees/{employee}/session', [EmployeeController::class, 'session']);
        Route::get('/positions', [PositionController::class, 'index']);
    });

    Route::middleware('capability:manage-schedules')->group(function () {
        Route::get('/teams', [TeamController::class, 'index']);
        Route::post('/teams', [TeamController::class, 'store']);
        Route::put('/teams/{team}', [TeamController::class, 'update']);
        Route::delete('/teams/{team}', [TeamController::class, 'destroy']);

        // The employee roster is an HR/admin concern, not a view-locations
        // one — it now carries phone numbers, usernames, active status,
        // and device identifiers (App\Http\Controllers\Api\V1\
        // EmployeeController::index()), none of which a supervisor's
        // capability is meant to reach. A supervisor still gets employee
        // names from /positions and window()/session() above, which is
        // all the live map needs.
        Route::get('/employees', [EmployeeController::class, 'index']);
        Route::post('/employees', [EmployeeController::class, 'store']);
        Route::put('/employees/{employee}/active', [EmployeeController::class, 'setActive']);
        Route::put('/employees/{employee}/password', [EmployeeController::class, 'resetPassword']);
        Route::delete('/employees/{employee}/device', [EmployeeController::class, 'revokeDevice']);

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
