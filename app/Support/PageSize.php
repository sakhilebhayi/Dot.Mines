<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * One page size for every list endpoint.
 *
 * The API had four different defaults (15, 20, 25 and 50) and, worse, seven
 * of its fifteen list endpoints ignored `per_page` altogether while the
 * documentation told everyone that all of them accepted it. So the parameter
 * you were told to use silently did nothing on nearly half the endpoints, and
 * the number of rows you got back depended on which one you happened to call.
 *
 * 15 is the default because it was already the most common (six endpoints)
 * and it is Laravel's own; 100 is the ceiling so one request cannot ask the
 * database for everything.
 */
final class PageSize
{
    public const DEFAULT = 15;

    public const MAX = 100;

    /**
     * The rule every list endpoint validates `per_page` with:
     *
     *     'per_page' => 'nullable|integer|min:1|max:100',
     *
     * Written out as a literal at each call site rather than referenced from
     * here on purpose. OpenApiGenerator builds the published parameter list by
     * reading the rule strings out of the controller source, so a rule given
     * as a constant would parse to nothing and `per_page` would quietly
     * disappear from the docs. PageSizeContractTest compares every one of
     * those literals against MAX, so the duplication cannot drift.
     */
    public const RULE_LITERAL = 'nullable|integer|min:1|max:100';

    /**
     * The page size for this request.
     *
     * Clamped as well as validated: the rule above returns a 422 for anything
     * over the ceiling, and this makes sure a value that reaches here by some
     * other path still cannot turn into an unbounded query.
     */
    public static function from(Request $request, int $default = self::DEFAULT): int
    {
        $requested = $request->input('per_page');

        if (! is_numeric($requested)) {
            return $default;
        }

        return max(1, min((int) $requested, self::MAX));
    }
}
