<?php

namespace App\Http\Middleware;

use Closure;
use Dot\SsoClient\Support\DeepLink;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `/sso/login?next=<in-app address>` (the team-invitation email's button, a bookmark) records the page the person was
 * going to, as the server-side intended URL the package already reads. The value passes the package's own DeepLink
 * check (same origin, one leading slash, no login/auth/machine path), anything else is dropped, and it is never echoed.
 * Does nothing while central sign-in is off, and never for any other path.
 */
class CaptureSsoNext
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('dotsso.enabled') && $request->isMethod('GET') && $request->is('sso/login') && $request->hasSession()) {
            $clean = DeepLink::clean($request->query('next'), $request);

            if ($clean !== null) {
                $request->session()->put('url.intended', $clean);
            }
        }

        $response = $next($request);
        assert($response instanceof Response);

        return $response;
    }
}
