<?php

namespace App\Providers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Responses\LoginResponse;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Requests\LoginRequest as FortifyLoginRequest;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Expose only the login and logout flows currently implemented.
        Fortify::ignoreRoutes();
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
        $this->app->bind(FortifyLoginRequest::class, LoginRequest::class);
    }

    public function boot(): void
    {
        Fortify::loginView(fn () => view('auth.login'));

        Fortify::authenticateUsing(function (Request $request): ?User {
            $user = User::query()->where('email', $request->input('email'))->first();

            return $user?->isActive() && Hash::check($request->input('password'), $user->password)
                ? $user
                : null;
        });
    }
}
