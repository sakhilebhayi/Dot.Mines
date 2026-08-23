<?php

use App\Http\Middleware\ApiVersion;
use App\Services\OpenApiGenerator;
use Illuminate\Support\Facades\Route;

/**
 * The API's composition root: which versions exist, and where each is served.
 *
 * The endpoints themselves live in routes/api_v1.php. This file decides what
 * they are called.
 *
 * Two spellings, one definition:
 *
 *   /api/v1/machines   the version to build against, and what the docs show
 *   /api/machines      the same endpoints, unversioned
 *
 * Both are registered from the same file with the same middleware, so they
 * cannot drift apart or differ in what they enforce.
 *
 * The rule that makes this safe: THE BARE PATHS ARE PINNED TO v1 FOREVER.
 * They are not an alias for "latest". When v2 arrives it is served at
 * /api/v2 only, and /api/machines keeps answering exactly as it does today.
 * Repointing the bare paths at a newer version would break every integration
 * that never asked to be upgraded, silently, on deploy day -- which is the
 * whole reason to have versions at all.
 *
 * This is deliberately not a deprecation with a sunset date. Redirecting or
 * retiring the bare paths buys nothing and costs a future outage for whoever
 * did not get the memo; keeping them costs one line here.
 */
Route::get('/openapi.json', function (OpenApiGenerator $generator) {
    return response()->json(
        $generator->cached(),
        200,
        [],
        JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
    );
})->name('api.openapi');

/**
 * Every authenticated endpoint runs this stack, in this order:
 * authenticate, check the token's permission for the verb, translate legacy
 * parameter names, resolve the team scope, then rate limit.
 *
 * @var list<string>
 */
$apiStack = [
    'auth:sanctum',
    'token.ability',
    'api.params',
    'ensure_team',
    'throttle:api',
    'api.version:'.ApiVersion::CURRENT,
];

// The current version, at its explicit path. This is what the docs describe.
Route::middleware($apiStack)
    ->prefix(ApiVersion::CURRENT)
    ->name('api.'.ApiVersion::CURRENT.'.')
    ->group(base_path('routes/api_v1.php'));

// The same endpoints unversioned, for everything integrated before versions
// existed. Pinned to v1 -- see the note above.
Route::middleware($apiStack)
    ->name('api.unversioned.')
    ->group(base_path('routes/api_'.ApiVersion::PINNED_FOR_UNVERSIONED.'.php'));
