<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fine-grained gate: `perm:students.create`. super_admin bypasses via
 * User::hasPermission(). Enforced server-side.
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->hasPermission($permission)) {
            abort(403, 'এই কাজটি করার অনুমতি আপনার নেই।');
        }

        return $next($request);
    }
}
