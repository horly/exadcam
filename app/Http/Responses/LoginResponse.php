<?php

namespace App\Http\Responses;

use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Responses\LoginResponse as FortifyLoginResponse;

class LoginResponse extends FortifyLoginResponse
{
    public function toResponse($request)
    {
        $request->user()->loginHistories()->create([
            'device' => $this->deviceName($request->userAgent()),
            'ip_address' => $request->ip(),
            'logged_in_at' => now(),
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'two_factor' => false,
                'redirect' => redirect()->intended(Fortify::redirects('login'))->getTargetUrl(),
            ]);
        }

        return parent::toResponse($request);
    }

    private function deviceName(?string $userAgent): string
    {
        $agent = (string) $userAgent;
        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'Firefox/') || str_contains($agent, 'FxiOS/') => 'Firefox',
            str_contains($agent, 'Chrome/') || str_contains($agent, 'CriOS/') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'Unknown browser',
        };
        $platform = match (true) {
            str_contains($agent, 'iPhone') || str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Mac OS') || str_contains($agent, 'Macintosh') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'unknown device',
        };

        return "{$browser} on {$platform}";
    }
}
