<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accept the API's older parameter names and translate them to the current
 * ones before anything else sees the request.
 *
 * The API grew three vocabularies for the same ideas -- a date range was
 * `start_date`/`end_date` on fuel and maintenance but `date_from`/`date_to`
 * on geofences, and a filter was `status`/`type` everywhere except machines,
 * where it was `filter_status`/`filter_type`. Muscle memory from one endpoint
 * actively misled you on the next.
 *
 * The endpoints now speak one vocabulary. This middleware keeps every
 * existing integration working: send the old name and it is understood. If
 * both are present the canonical name wins, so a client can migrate one
 * parameter at a time without ambiguity.
 *
 * Retire an alias only after the deprecation is published and the access logs
 * show nobody sending it.
 */
class NormalizeApiParameters
{
    /**
     * Legacy parameter name => the name the API uses now.
     *
     * @var array<string, string>
     */
    public const ALIASES = [
        'date_from' => 'start_date',
        'date_to' => 'end_date',
        'filter_status' => 'status',
        'filter_type' => 'type',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        foreach (self::ALIASES as $legacy => $canonical) {
            // The canonical name wins when a client sends both.
            if ($request->has($legacy) && ! $request->has($canonical)) {
                /** @psalm-suppress MixedAssignment */
                $value = $request->input($legacy);

                $request->merge([$canonical => $value]);

                // Also mirror into the query bag: rules like `required` read
                // merged input, but anything reaching for ->query() directly
                // would otherwise miss a value sent under the legacy name.
                if (is_scalar($value) || is_array($value) || $value === null) {
                    $request->query->set($canonical, $value);
                }
            }
        }

        $response = $next($request);
        assert($response instanceof Response);

        return $response;
    }
}
