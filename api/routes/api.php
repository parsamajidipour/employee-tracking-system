<?php

use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\V1\AdminProfileController;
use App\Http\Controllers\Api\V1\AppReleaseController;
use App\Http\Controllers\Api\V1\DeviceAuthController;
use App\Http\Controllers\Api\V1\EmployeeController;
use App\Http\Controllers\Api\V1\EmployeeHistoryController;
use App\Http\Controllers\Api\V1\EmployeeShiftController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\PositionController;
use App\Http\Controllers\Api\V1\ShiftExceptionController;
use App\Http\Controllers\Api\V1\ShiftTemplateController;
use App\Http\Controllers\Api\V1\TrackController;
use Illuminate\Support\Facades\Route;

Route::get('/v1/app/latest-version', [AppReleaseController::class, 'latest']);
Route::get('/app-releases/{appRelease}/download', [AppReleaseController::class, 'download'])->name('app-releases.download');

Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:10,1');
Route::post('/v1/device/login', [DeviceAuthController::class, 'login'])->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [SessionController::class, 'show']);
    Route::post('/logout', [SessionController::class, 'destroy']);
    Route::put('/v1/admin/profile', [AdminProfileController::class, 'update']);

    Route::prefix('v1')->group(function () {
        Route::post('/track', [TrackController::class, 'store']);
        Route::get('/me/window', [MeController::class, 'window']);

        Route::middleware('capability:view-locations')->group(function () {
            Route::get('/positions', [PositionController::class, 'index']);
            Route::get('/employees/{employee}/distance', [PositionController::class, 'distance']);
            Route::get('/employees/{employee}/trail', [EmployeeHistoryController::class, 'trail']);
            Route::get('/employees/{employee}/histories', [EmployeeHistoryController::class, 'index']);
            Route::get('/employees/{employee}/window', [EmployeeController::class, 'window']);
            Route::get('/employees/{employee}/session', [EmployeeController::class, 'session']);
        });

        Route::middleware('capability:manage-schedules')->group(function () {
            Route::get('/employees', [EmployeeController::class, 'index']);
            Route::post('/employees', [EmployeeController::class, 'store']);
            Route::put('/employees/{employee}/active', [EmployeeController::class, 'setActive']);
            Route::put('/employees/{employee}/password', [EmployeeController::class, 'resetPassword']);
            Route::delete('/employees/{employee}/device', [EmployeeController::class, 'revokeDevice']);
            Route::put('/employees/{employee}/shifts', [EmployeeController::class, 'syncShifts']);
            Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy']);

            Route::apiResource('shift-templates', ShiftTemplateController::class)->except('show');
            Route::apiResource('shift-exceptions', ShiftExceptionController::class)->except('show');
            Route::apiResource('employee-shifts', EmployeeShiftController::class)->except('show');
        });

        Route::middleware('capability:manage-releases')->group(function () {
            Route::get('/app-releases', [AppReleaseController::class, 'index']);
            Route::post('/app-releases', [AppReleaseController::class, 'store']);
            Route::delete('/app-releases/{appRelease}', [AppReleaseController::class, 'destroy']);
        });
    });
});
