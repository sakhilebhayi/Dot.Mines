<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnforceDownloadRateLimit Middleware
 *
 * Applies a downloads-specific rate limit for endpoints that look like
 * download endpoints (path contains "download" or route name contains "download").
 */
class EnforceDownloadRateLimit
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->path();
        $routeName = $request->route()?->getName() ?? '';

        // Apply only to endpoints that appear to be download endpoints
        if (Str::contains($path, 'download') || Str::contains($routeName, 'download')) {
            $key = 'downloads|'.($request->user()?->id ?: $request->ip());
            $maxAttempts = 10; // keep in sync with AppServiceProvider::configureRateLimiting()
            $decaySeconds = 60;

            if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                return response()->json([
                    'message' => 'Download rate limit exceeded.',
                    'retry_after' => RateLimiter::availableIn($key),
                ], 429);
            }

            RateLimiter::hit($key, $decaySeconds);
        }

        return $next($request);
    }
}
