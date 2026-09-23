<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasClientPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        abort_unless($request->user()?->hasClientPermission($permission), 403);

        return $next($request);
    }
}
