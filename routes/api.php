<?php

use App\Http\Controllers\ListenerController;
use App\Http\Middleware\AuthenticateListener;
use Illuminate\Support\Facades\Route;

Route::prefix('internal/listener')->middleware(AuthenticateListener::class)->group(function () {
    Route::post('resolve', [ListenerController::class, 'resolve']);
    Route::post('event', [ListenerController::class, 'event']);
    Route::post('audio-access', [ListenerController::class, 'audioAccess']);
});
