<?php

use App\Http\Controllers\AudioController;
use App\Http\Controllers\DashboardPreviewController;
use App\Http\Controllers\DashcamController;
use App\Http\Controllers\FleetRegistryController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserLoginHistoryController;
use App\Http\Middleware\PreventPageCaching;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

Route::middleware(PreventPageCaching::class)->group(function () {
    Route::post('/language/{locale}', LocaleController::class)
        ->whereIn('locale', array_keys(config('localization.supported')))
        ->name('locale.update');

    Route::middleware('guest')->group(function () {
        Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    });

    Route::middleware(['auth', 'active'])->group(function () {
        Route::get('/', DashboardPreviewController::class)->name('dashboard');
        Route::get('/dashboard/data', [DashboardPreviewController::class, 'data'])->middleware('throttle:20,1,dashboard-data:')->name('dashboard.data');
        Route::get('/dashboard/alerts', [DashboardPreviewController::class, 'alerts'])->middleware('throttle:30,1,dashboard-alerts:')->name('dashboard.alerts');
        Route::get('/map/vehicles', [MapController::class, 'vehicles'])->middleware('throttle:30,1,map-vehicles:')->name('map.vehicles');
        Route::get('/map/vehicles/{vehicle}/details', [MapController::class, 'details'])->whereNumber('vehicle')->middleware('throttle:30,1,map-details:')->name('map.details');
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
