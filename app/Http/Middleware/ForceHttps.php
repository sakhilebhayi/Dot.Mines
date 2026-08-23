<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ForceHttps Middleware
 *
 * Redirects HTTP requests to HTTPS in production environments.
 */
class ForceHttps
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only enforce in production
        if (app()->environment('production')) {
            // If the request is not secure, redirect to HTTPS
            if (! $request->isSecure()) {
                $secureUrl = 'https://'.$request->getHttpHost().$request->getRequestUri();

                return redirect()->secure($request->getRequestUri(), 301);
            }
        }

        $response = $next($request);
        assert($response instanceof Response);

        return $response;
    }
}
