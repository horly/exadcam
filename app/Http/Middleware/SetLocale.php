<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale', $request->cookie('exadcam_locale'));

        if (! is_string($locale) || ! array_key_exists($locale, config('localization.supported'))) {
            $locale = config('localization.default');
        }

        App::setLocale($locale);

        return $next($request);
    }
}
