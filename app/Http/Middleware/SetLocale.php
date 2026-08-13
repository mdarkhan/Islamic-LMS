<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the interface language for the request.
 *
 * Precedence: the authenticated user's saved preference, else the session (so a
 * guest's choice survives login), else the app default. Only the two supported UI
 * locales are honoured — everything else falls back to the default. This affects the
 * interface chrome only; content stays Bengali regardless.
 */
class SetLocale
{
    public const SUPPORTED = ['bn', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale
            ?? $request->session()->get('locale')
            ?? config('app.locale');

        if (! in_array($locale, self::SUPPORTED, true)) {
            $locale = config('app.locale');
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
