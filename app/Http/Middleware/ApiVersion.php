<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tells the caller which version of the API answered them.
 *
 * The same endpoints are reachable at `/api/v1/...` and, for everything
 * integrated before versions existed, bare at `/api/...`. A client on the
 * bare path has no way to tell from the URL which contract it is getting, so
 * every response carries `X-API-Version`, and bare responses additionally
 * carry a `Link: ...; rel="successor-version"` header naming the exact
 * versioned URL to move to. That turns "you should migrate one day" into
 * something a client can read, log, and act on without a human reading docs.
 */
class ApiVersion
{
    /**
     * The version served at the explicit path, documented and current.
     */
    public const CURRENT = 'v1';

    /**
     * The version the bare `/api/...` paths resolve to -- permanently.
     *
     * This is a separate constant from CURRENT on purpose. When v2 ships,
     * CURRENT moves and this one does NOT: the bare paths keep answering as
     * v1 so integrations that never asked to be upgraded are not broken by a
     * deploy. If you are here to make these equal, you are about to break
     * every client that omitted the version.
     */
    public const PINNED_FOR_UNVERSIONED = 'v1';

    public function handle(Request $request, Closure $next, string $version): Response
    {
        $response = $next($request);
        assert($response instanceof Response);

        $response->headers->set('X-API-Version', $version);

        $path = '/'.ltrim($request->path(), '/');

        if (! str_starts_with($path, "/api/{$version}/")) {
            $response->headers->set(
                'Link',
                '<'.url('/api/'.$version.substr($path, strlen('/api'))).'>; rel="successor-version"'
            );
        }

        return $response;
    }
}
