<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security headers on every response. Deliberately NOT a Content-Security-
 * Policy: this app relies on Alpine's inline directives, an inline pre-paint theme
 * script (base.blade.php), and embeds Google Drive/YouTube iframes — a correct CSP for
 * all of that needs careful nonce/host tuning and its own testing pass, not a
 * drive-by addition here.
 *
 * - X-Frame-Options: DENY — stops this site's own pages (admin login, the live exam)
 *   from being framed elsewhere for clickjacking. Does not affect this app framing
 *   OTHER sites (Drive/YouTube embeds), which is the opposite direction.
 * - X-Content-Type-Options: nosniff — stops the browser guessing a different MIME type
 *   for an upload (e.g. a book cover) than what it was validated and stored as.
 * - Referrer-Policy: only the origin leaves this site in a cross-site Referer header;
 *   the full path (which could include a token, e.g. the credential-export link) never
 *   does.
 * - Strict-Transport-Security: only sent over an already-HTTPS request — sending it
 *   over plain HTTP would do nothing (browsers ignore it) and could be misleading in
 *   local dev.
 */
class AddSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
