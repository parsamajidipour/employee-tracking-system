<?php

use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\V1\AdminProfileController;
use App\Http\Controllers\Api\V1\AppReleaseController;
use App\Http\Controllers\Api\V1\CaseController;
use App\Http\Controllers\Api\V1\CasePhotoController;
use App\Http\Controllers\Api\V1\DeviceAuthController;
use App\Http\Controllers\Api\V1\EmployeeController;
use App\Http\Controllers\Api\V1\EmployeeHistoryController;
use App\Http\Controllers\Api\V1\EmployeeShiftController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\MyCaseController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PositionController;
use App\Http\Controllers\Api\V1\ShiftExceptionController;
use App\Http\Controllers\Api\V1\ShiftTemplateController;
use App\Http\Controllers\Api\V1\TrackController;
use App\Http\Controllers\Api\V1\TrackingInterruptionController;
use App\Http\Controllers\Api\V1\WorkloadController;
use Illuminate\Support\Facades\Route;

Route::get('/v1/app/latest-version', [AppReleaseController::class, 'latest']);
Route::get('/app-releases/{appRelease}/download', [AppReleaseController::class, 'download'])->name('app-releases.download');

Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:10,1');
Route::post('/v1/device/login', [DeviceAuthController::class, 'login'])->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [SessionController::class, 'show']);
    Route::post('/logout', [SessionController::class, 'destroy']);
    Route::put('/v1/admin/profile', [AdminProfileController::class, 'update']);
    Route::put('/v1/admin/password', [AdminProfileController::class, 'updatePassword']);

    Route::prefix('v1')->group(function () {
        Route::post('/device/logout', [DeviceAuthController::class, 'logout']);
        Route::post('/track', [TrackController::class, 'store']);
        Route::post('/track/ping', [TrackController::class, 'ping']);
        Route::get('/me/window', [MeController::class, 'window']);
        Route::post('/tracking-interruptions/start', [TrackingInterruptionController::class, 'start']);
        Route::post('/tracking-interruptions/stop', [TrackingInterruptionController::class, 'stop']);

        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);

        Route::get('/me/cases', [MyCaseController::class, 'index']);
        Route::get('/me/cases/unseen-count', [MyCaseController::class, 'unseenCount']);
        Route::get('/me/cases/{case}', [MyCaseController::class, 'show']);
        Route::post('/me/cases/{case}/accept', [MyCaseController::class, 'accept']);
        Route::post('/me/cases/{case}/reject', [MyCaseController::class, 'reject']);
        Route::post('/me/cases/{case}/start', [MyCaseController::class, 'start']);
        Route::post('/me/cases/{case}/complete', [MyCaseController::class, 'complete']);
        Route::post('/me/cases/{case}/photos', [CasePhotoController::class, 'store']);
        Route::get('/case-photos/{casePhoto}', [CasePhotoController::class, 'show'])->name('case-photos.show');

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
            Route::put('/employees/{employee}', [EmployeeController::class, 'update']);
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

        Route::middleware('capability:view-cases')->group(function () {
            Route::get('/cases', [CaseController::class, 'index']);
            Route::get('/cases/{case}', [CaseController::class, 'show']);
            Route::get('/cases/{case}/nearest-surveyors', [CaseController::class, 'nearestSurveyors']);
            Route::get('/workload', [WorkloadController::class, 'index']);
            Route::get('/workload/{employee}', [WorkloadController::class, 'show']);
        });

        Route::middleware('capability:manage-cases')->group(function () {
            Route::post('/cases', [CaseController::class, 'store']);
            Route::post('/cases/{case}/assign', [CaseController::class, 'assign']);
            Route::post('/cases/{case}/cancel', [CaseController::class, 'cancel']);
            Route::delete('/cases/{case}', [CaseController::class, 'destroy']);
        });
    });
});
