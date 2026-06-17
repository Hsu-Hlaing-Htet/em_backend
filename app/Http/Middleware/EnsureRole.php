<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user()?->loadMissing('role');
        $roleName = $user?->role?->name;

        if (! $user || ! in_array($roleName, $roles, true)) {
            abort(403, 'Unauthorized role access.');
        }

        return $next($request);
    }
}
