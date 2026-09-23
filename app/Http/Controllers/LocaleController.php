<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class LocaleController extends Controller
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        $request->session()->put('locale', $locale);

        return to_route($request->user() ? 'dashboard' : 'login')->withCookie(Cookie::make(
            name: 'exadcam_locale',
            value: $locale,
            minutes: 60 * 24 * 365,
            path: '/',
            secure: config('session.secure'),
            httpOnly: true,
            sameSite: 'lax',
        ));
    }
}
