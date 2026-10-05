<?php

use Illuminate\Support\Facades\Route;

use App\Modules\Authentication\Controllers\AuthController;
use App\Modules\Authentication\Controllers\GoogleAuthController;
use App\Modules\ReferenceData\Controllers\GeographyController;
use App\Modules\ReferenceData\Controllers\PollingUnitSubmissionController;
use App\Modules\Incidents\Controllers\IncidentController;
use App\Modules\Observers\Controllers\CheckInController;
use App\Modules\Observers\Controllers\LocationController;
use App\Modules\Assignments\Controllers\AssignmentController;
use App\Modules\Users\Controllers\UserController;
use App\Modules\GIS\Controllers\GISController;
use App\Modules\Reports\Controllers\ReportController;
use App\Modules\Notifications\Controllers\NotificationController;
use App\Modules\Audit\Controllers\AuditController;
use App\Modules\Roles\Controllers\RoleController;
use App\Modules\Roles\Controllers\PermissionController;
use App\Modules\Dashboard\Controllers\DashboardController;
use App\Modules\Inbox\Controllers\InboxController;
use App\Modules\Elections\Controllers\ElectionScheduleController;

/*
|--------------------------------------------------------------------------
| API Routes - Election Intelligence Platform
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // ── Public ───────────────────────────────────────────────────────
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/google', [GoogleAuthController::class, 'login']);
    Route::get('/geography/states',      [GeographyController::class, 'states']);
    Route::get('/geography/categories',  [GeographyController::class, 'categories']);

    // ── Protected ────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum', 'device.bind', \App\Modules\Authentication\Middleware\SetPermissionsTeam::class])->group(function () {

        // Auth
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // Geography (state-scoped)
        Route::prefix('geography')->group(function () {
            Route::get('/lgas',          [GeographyController::class, 'lgas']);
            Route::get('/wards',         [GeographyController::class, 'wards']);
            Route::get('/polling-units', [GeographyController::class, 'pollingUnits']);
        });

        // Incidents
        Route::prefix('incidents')->group(function () {
            Route::get('/',        [IncidentController::class, 'index']);
            Route::post('/report', [IncidentController::class, 'store']);
            Route::post('/media',  [IncidentController::class, 'uploadMedia']);
            Route::get('/{id}',    [IncidentController::class, 'show']);
        });

        // Observers
        Route::prefix('observers')->group(function () {
            Route::post('/check-in', [CheckInController::class, 'store']);
            Route::post('/location', [LocationController::class, 'store']);
        });

        // Polling unit location submissions (observer capture → admin review)
        Route::prefix('polling-units/submissions')->group(function () {
            Route::get('/mine',             [PollingUnitSubmissionController::class, 'mine']);
            Route::get('/',                 [PollingUnitSubmissionController::class, 'index']);
            Route::post('/',                [PollingUnitSubmissionController::class, 'store']);
            Route::get('/{id}/photos/{photoId}', [PollingUnitSubmissionController::class, 'photo']);
            Route::post('/{id}/approve',    [PollingUnitSubmissionController::class, 'approve']);
            Route::post('/{id}/reject',     [PollingUnitSubmissionController::class, 'reject']);
        });

        // Assignments
        Route::prefix('assignments')->group(function () {
            Route::get('/mine',    [AssignmentController::class, 'mine']);
            Route::get('/',        [AssignmentController::class, 'index']);
            Route::post('/',       [AssignmentController::class, 'store']);
            Route::post('/bulk',   [AssignmentController::class, 'bulk']);
            Route::delete('/{id}', [AssignmentController::class, 'destroy']);
        });

        // Users
        Route::prefix('users')->group(function () {
            Route::get('/',                  [UserController::class, 'index']);
            Route::post('/',                 [UserController::class, 'store']);
            Route::get('/{id}',              [UserController::class, 'show']);
            Route::put('/{id}',              [UserController::class, 'update']);
            Route::delete('/{id}',           [UserController::class, 'destroy']);
            Route::post('/{id}/suspend',     [UserController::class, 'suspend']);
            Route::post('/{id}/assign-role', [UserController::class, 'assignRole']);
        });

        // Roles & Permissions
        Route::prefix('roles')->group(function () {
            Route::get('/',        [RoleController::class, 'index']);
            Route::post('/',       [RoleController::class, 'store']);
            Route::get('/{id}',    [RoleController::class, 'show']);
            Route::put('/{id}',    [RoleController::class, 'update']);
            Route::delete('/{id}', [RoleController::class, 'destroy']);
        });
        Route::get('/permissions', [PermissionController::class, 'index']);

        // Dashboard
        Route::prefix('dashboard')->group(function () {
            Route::get('/metrics',        [DashboardController::class, 'metrics']);
            Route::get('/incidents',      [DashboardController::class, 'incidents']);
            Route::get('/activity',       [DashboardController::class, 'activity']);
            Route::get('/activity-chart', [DashboardController::class, 'activityChart']);
            Route::get('/status',         [DashboardController::class, 'status']);
        });

        // GIS
        Route::prefix('gis')->group(function () {
            Route::get('/polling-units', [GISController::class, 'pollingUnits']);
            Route::get('/observers',     [GISController::class, 'observers']);
            Route::get('/incidents',     [GISController::class, 'incidents']);
        });

        // Reports
        Route::prefix('reports')->group(function () {
            Route::get('/incidents', [ReportController::class, 'incidents']);
            Route::get('/observers', [ReportController::class, 'observers']);
            Route::get('/summary',   [ReportController::class, 'summary']);
        });

        // Inbox (tenant-scoped notifications)
        Route::prefix('inbox')->group(function () {
            Route::get('/',              [InboxController::class, 'index']);
            Route::post('/read-all',     [InboxController::class, 'markAllRead']);
            Route::post('/{id}/read',    [InboxController::class, 'markRead']);
        });

        // Elections
        Route::get('/elections/upcoming', [ElectionScheduleController::class, 'upcoming']);

        // Notifications
        Route::prefix('notifications')->group(function () {
            Route::get('/',           [NotificationController::class, 'index']);
            Route::post('/{id}/read', [NotificationController::class, 'markRead']);
        });

        // Audit (super-admin only)
        Route::get('/audit/logs', [AuditController::class, 'index']);

    });

});
