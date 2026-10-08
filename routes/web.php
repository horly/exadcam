<?php

use App\Http\Controllers\AudioController;
use App\Http\Controllers\CustomizationController;
use App\Http\Controllers\DashboardPreviewController;
use App\Http\Controllers\DashcamController;
use App\Http\Controllers\FleetRegistryController;
use App\Http\Controllers\FleetReportController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\RecordingController;
use App\Http\Controllers\ServerLogController;
use App\Http\Controllers\ServerMonitoringController;
use App\Http\Controllers\SmartvisionConfigurationController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserLoginHistoryController;
use App\Http\Middleware\PreventPageCaching;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

Route::middleware(PreventPageCaching::class)->group(function () {
    Route::post('/language/{locale}', LocaleController::class)
        ->whereIn('locale', array_keys(config('localization.supported')))
        ->name('locale.update');

    Route::get('/branding/global/{kind}/{version}', [CustomizationController::class, 'globalImage'])->whereIn('kind', ['logo', 'internal_logo', 'favicon'])->where('version', '[0-9a-f]{20}')->name('branding.global-image');

    Route::middleware('guest')->group(function () {
        Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    });

    Route::middleware(['auth', 'active'])->group(function () {
        Route::get('/', DashboardPreviewController::class)->name('dashboard');
        Route::prefix('reports')->middleware('throttle:60,1,reports:')->group(function () {
            $controller = FleetReportController::class;
            Route::get('/options', [$controller, 'options'])->name('reports.options');
            Route::post('/runs', [$controller, 'store'])->middleware('throttle:6,1,reports-create:')->name('reports.store');
            Route::get('/runs/{run}', [$controller, 'show'])->whereUuid('run')->name('reports.show');
            Route::get('/runs/{run}/export', [$controller, 'export'])->whereUuid('run')->middleware('throttle:10,1,reports-export:')->name('reports.export');
            Route::get('/runs/{run}/details/{kind}/{index}', [$controller, 'detail'])->whereUuid('run')->whereIn('kind', ['trips', 'safety'])->whereNumber('index')->name('reports.detail');
            Route::post('/presets', [$controller, 'savePreset'])->name('reports.presets.store');
            Route::delete('/presets/{preset}', [$controller, 'deletePreset'])->whereNumber('preset')->name('reports.presets.delete');
        });
        Route::post('/customization/global', [CustomizationController::class, 'updateGlobal'])->middleware(['superadmin', 'throttle:10,1,customization:'])->name('customization.global');
        Route::post('/customization/fleet', [CustomizationController::class, 'updateFleet'])->middleware('throttle:10,1,customization:')->name('customization.fleet');
        Route::get('/branding/fleet/logo/{version}', [CustomizationController::class, 'fleetImage'])->where('version', '[0-9a-f]{20}')->name('branding.fleet-image');
        Route::get('/server-monitoring/metrics', [ServerMonitoringController::class, 'metrics'])->middleware(['superadmin', 'throttle:40,1,server-monitoring:'])->name('server-monitoring.metrics');
        Route::get('/server-logs/content', [ServerLogController::class, 'content'])->middleware(['superadmin', 'throttle:40,1,server-logs:'])->name('server-logs.content');
        Route::get('/dashboard/data', [DashboardPreviewController::class, 'data'])->middleware('throttle:20,1,dashboard-data:')->name('dashboard.data');
        Route::get('/dashboard/alerts/recent', [DashboardPreviewController::class, 'recentAlerts'])->middleware('throttle:30,1,dashboard-recent-alerts:')->name('dashboard.alerts.recent');
        Route::get('/dashboard/alerts', [DashboardPreviewController::class, 'alerts'])->middleware('throttle:30,1,dashboard-alerts:')->name('dashboard.alerts');
        Route::get('/map/vehicles', [MapController::class, 'vehicles'])->middleware('throttle:30,1,map-vehicles:')->name('map.vehicles');
        Route::get('/map/vehicles/{vehicle}/trips', [MapController::class, 'trips'])->whereNumber('vehicle')->middleware('throttle:20,1,map-trips:')->name('map.trips');
        Route::get('/map/vehicles/{vehicle}/details', [MapController::class, 'details'])->whereNumber('vehicle')->middleware('throttle:30,1,map-details:')->name('map.details');
        Route::post('/dashcams/{dashcam}/recordings/search', [RecordingController::class, 'search'])->whereNumber('dashcam')->middleware('throttle:6,1,recordings-search:');
        Route::get('/dashcams/{dashcam}/recordings/search/{query}', [RecordingController::class, 'results'])->whereNumber('dashcam')->whereUuid('query');
        Route::post('/dashcams/{dashcam}/recordings/jobs', [RecordingController::class, 'prepare'])->whereNumber('dashcam')->middleware('throttle:10,1,recordings-prepare:');
        Route::get('/dashcams/{dashcam}/recordings/jobs/{job}', [RecordingController::class, 'status'])->whereNumber('dashcam')->whereUuid('job')->middleware('throttle:60,1,recordings-status:');
        Route::post('/dashcams/{dashcam}/recordings/jobs/{job}/cancel', [RecordingController::class, 'cancel'])->whereNumber('dashcam')->whereUuid('job');
        Route::get('/dashcams/{dashcam}/recordings/jobs/{job}/video', [RecordingController::class, 'media'])->whereNumber('dashcam')->whereUuid('job');
        Route::middleware(['superadmin', 'throttle:20,1,smartvision-config:'])->group(function () {
            Route::get('/dashcams/{dashcam}/configuration', [SmartvisionConfigurationController::class, 'show'])->whereNumber('dashcam')->name('dashcams.configuration.show');
            Route::put('/dashcams/{dashcam}/configuration', [SmartvisionConfigurationController::class, 'update'])->whereNumber('dashcam')->name('dashcams.configuration.update');
        });
        Route::get('/dashcams/options', [DashcamController::class, 'options'])->name('dashcams.options');
        Route::post('/dashcams/{dashcam}/audio', [AudioController::class, 'start'])->whereNumber('dashcam')->middleware('throttle:20,1,audio-start:');
        Route::post('/dashcams/{dashcam}/audio/{lease}/keepalive', [AudioController::class, 'keepalive'])->whereNumber('dashcam')->whereUuid('lease')->middleware('throttle:30,1,audio-keepalive:');
        Route::post('/dashcams/{dashcam}/audio/{lease}/stop', [AudioController::class, 'stop'])->whereNumber('dashcam')->whereUuid('lease')->middleware('throttle:30,1,audio-stop:');
        Route::get('/registry/{kind}', [FleetRegistryController::class, 'index'])->whereIn('kind', ['fleets', 'vehicles', 'departments']);
        Route::post('/registry/{kind}', [FleetRegistryController::class, 'store'])->whereIn('kind', ['fleets', 'vehicles', 'departments']);
        Route::patch('/registry/{kind}/{id}', [FleetRegistryController::class, 'update'])->whereIn('kind', ['fleets', 'vehicles', 'departments'])->whereNumber('id');
        Route::get('/dashcams', [DashcamController::class, 'index'])->name('dashcams.index');
        Route::post('/dashcams', [DashcamController::class, 'store'])->middleware('superadmin')->name('dashcams.store');
        Route::patch('/dashcams/{dashcam}', [DashcamController::class, 'update'])->name('dashcams.update');
        Route::post('/dashcams/{dashcam}/live', [DashcamController::class, 'start'])->middleware('throttle:20,1,video-start:');
        Route::post('/dashcams/{dashcam}/live/{lease}/keepalive', [DashcamController::class, 'keepalive'])->whereUuid('lease');
        Route::post('/dashcams/{dashcam}/live/{lease}/stop', [DashcamController::class, 'stop'])->whereUuid('lease');
        Route::get('/users/options', [UserController::class, 'options'])->name('users.options');
        Route::get('/users/{user}/login-history', UserLoginHistoryController::class)->name('users.history');
        Route::resource('users', UserController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    });
});
