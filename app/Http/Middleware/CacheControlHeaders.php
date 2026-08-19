<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cache Control Headers Middleware
 *
 * Adds appropriate caching headers to API responses
 * for better performance and reduced server load
 */
class CacheControlHeaders
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $duration = 'short'): Response
    {
        $response = $next($request);

        // Don't cache if not successful or if it's an error
        if ($response->getStatusCode() !== 200) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');

            return $response;
        }

        // Define cache durations in seconds
        $durations = [
            'none' => 0,
            'short' => 60,          // 1 minute
            'medium' => 300,        // 5 minutes
            'long' => 3600,         // 1 hour
            'static' => 86400,      // 24 hours
        ];

        $seconds = $durations[$duration] ?? $durations['short'];

        if ($seconds > 0) {
            $response->headers->set('Cache-Control', "public, max-age={$seconds}");
            $response->headers->set('Expires', gmdate('D, d M Y H:i:s', time() + $seconds).' GMT');
        } else {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');
        }

        return $response;
    }
}
