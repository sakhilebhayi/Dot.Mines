<?php

namespace App\Http\Middleware;

use App\Support\ApiPayload;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bearer-token gate for the guardian observability endpoint.
 *
 * The guardian report exposes operational internals (queue depth, error
 * counts, per-integration sync lag), so unlike /health it is never public.
 * Fails closed: no configured token means 503, not open access.
 *
 * @psalm-suppress UnusedClass -- registered via the 'guardian.token'
 * middleware alias in bootstrap/app.php, which psalm cannot trace.
 */
final class AuthenticateGuardian
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $configured = ApiPayload::str(config('guardian.token'));

        if ($configured === '') {
            return response()->json([
                'message' => 'Guardian endpoint is not configured.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $presented = $request->bearerToken();

        if (! is_string($presented) || ! hash_equals($configured, $presented)) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
