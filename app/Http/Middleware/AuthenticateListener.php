<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateListener
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) config('listener.token');
        abort_unless(in_array($request->server('REMOTE_ADDR'), ['127.0.0.1', '::1'], true)
            && strlen($token) >= 32 && hash_equals($token, (string) $request->bearerToken()), 403);

        return $next($request);
    }
}
