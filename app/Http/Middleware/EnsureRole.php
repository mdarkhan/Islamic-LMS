<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Coarse gate for an area: `role:super_admin,admin`. Enforced server-side, never
 * by hidden navigation alone.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->hasAnyRole(...$roles)) {
            abort(403, 'এই অংশে প্রবেশের অনুমতি আপনার নেই।');
        }

        return $next($request);
    }
}
