<?php

namespace App\Providers;

use App\Services\BrandingService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();
        View::composer(['welcome', 'auth.login', 'errors.419'], function ($view): void {
            $view->with('branding', app(BrandingService::class)->context(auth()->user()));
        });
    }
}
