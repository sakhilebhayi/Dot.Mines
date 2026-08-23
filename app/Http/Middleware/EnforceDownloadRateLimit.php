<?php

namespace App\Http\Middleware;

use App\Support\CurrentUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
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
        // Psalm types $request->route() as Route; phpstan as Route|object|string|null.
        $route = $request->route();
        /** @psalm-suppress RedundantConditionGivenDocblockType */
        $routeName = $route instanceof Route ? ($route->getName() ?? '') : '';

        // Apply only to endpoints that appear to be download endpoints
        if (Str::contains($path, 'download') || Str::contains($routeName, 'download')) {
            $key = 'downloads|'.((string) (CurrentUser::get()?->id ?? $request->ip() ?? 'unknown'));
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

        $response = $next($request);
        assert($response instanceof Response);

        return $response;
    }
}
