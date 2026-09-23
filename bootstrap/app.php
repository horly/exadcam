<?php

use App\Http\Middleware\EnsureUserHasClientPermission;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserIsSuperadmin;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Session\Middleware\StartSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'superadmin' => EnsureUserIsSuperadmin::class,
            'client.permission' => EnsureUserHasClientPermission::class,
        ]);
        $middleware->web(append: [SetLocale::class]);
        $middleware->appendToPriorityList(after: StartSession::class, append: SetLocale::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
