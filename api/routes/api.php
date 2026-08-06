<?php

use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\TrackController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

// Minimal session-cookie login for the Sanctum SPA flow (Nuxt panel/).
// Real roles (admin/hr/supervisor/employee) land with the actual auth work —
// this only proves the CSRF-cookie + session-cookie plumbing end to end.
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
});
