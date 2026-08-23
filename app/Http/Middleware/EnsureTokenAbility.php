<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforce personal-access-token abilities across the whole API by HTTP verb.
 *
 * Sanctum issues tokens with abilities (create/read/update/delete — the same
 * vocabulary as Jetstream's roles and the API Tokens UI), but nothing ever
 * checked them: a token minted with only 'read' could DELETE. This closes
 * that gap once, at the group level, so every current and future route is
 * covered without per-route wiring.
 *
 * First-party/session requests (the Livewire console, the browser sync
 * client, live-map) are unaffected: Sanctum authenticates them with a
 * TransientToken whose can() always returns true, so tokenCan() passes.
 * Only real personal access tokens carry finite abilities.
 *
 * POST accepts create OR update, because not every POST is a creation —
 * acknowledging an alert, triggering a sync, or completing a work order are
 * state changes an 'update' token should be allowed to make. A read-only
 * token holds none of the write abilities and is refused on every mutation.
 */
class EnsureTokenAbility
{
    public function handle(Request $request, Closure $next): Response
    {
        $required = match ($request->getMethod()) {
            'POST' => ['create', 'update'],
            'PUT', 'PATCH' => ['update'],
            'DELETE' => ['delete'],
            default => ['read'], // GET, HEAD, OPTIONS
        };

        $user = $request->user();

        if ($user instanceof User) {
            $permitted = false;

            foreach ($required as $ability) {
                if ($user->tokenCan($ability)) {
                    $permitted = true;
                    break;
                }
            }

            if (! $permitted) {
                return response()->json([
                    'message' => 'This API token is missing the "'.implode('" or "', $required).'" ability required for this request.',
                ], 403);
            }
        }

        $response = $next($request);
        assert($response instanceof Response);

        return $response;
    }
}
