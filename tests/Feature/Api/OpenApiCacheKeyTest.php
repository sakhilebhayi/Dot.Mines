<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Api\MachineController;
use App\Services\OpenApiGenerator;
use Illuminate\Support\Facades\Route;
use ReflectionMethod;
use Tests\TestCase;

/**
 * The published spec must never describe code that is no longer deployed.
 *
 * This was a real production bug, not a hypothetical: the spec was cached for
 * an hour under a fixed key, so renaming a query parameter shipped the code
 * but kept serving the previous description until the TTL happened to expire.
 * A generated spec that can lag behind its own source is worse than a
 * hand-written one, because nobody thinks to check it.
 *
 * The key now carries a fingerprint of the route table and the controller
 * files, so anything that changes the spec also changes where it is stored.
 */
class OpenApiCacheKeyTest extends TestCase
{
    public function test_the_key_is_stable_when_nothing_changes(): void
    {
        $generator = app(OpenApiGenerator::class);

        // Caching is worthless if the key moves on its own.
        $this->assertSame($generator->cacheKey('spec'), $generator->cacheKey('spec'));
    }

    public function test_the_spec_and_the_docs_page_do_not_share_a_key(): void
    {
        $generator = app(OpenApiGenerator::class);

        $this->assertNotSame($generator->cacheKey('spec'), $generator->cacheKey('reference'));
    }

    public function test_adding_an_endpoint_changes_the_key(): void
    {
        $generator = app(OpenApiGenerator::class);
        $before = $generator->cacheKey('spec');

        Route::get('api/cache-key-probe', [MachineController::class, 'index']);

        $this->assertNotSame(
            $before,
            $generator->cacheKey('spec'),
            'A new endpoint must not be served from a spec generated before it existed.'
        );
    }

    public function test_redeploying_a_controller_changes_the_key(): void
    {
        $generator = app(OpenApiGenerator::class);
        $before = $generator->cacheKey('spec');

        // A deploy rewrites the file, moving its timestamp. That is the signal
        // the parameter rename needed and did not have: the routes were
        // identical, only the validation rules inside the controller moved.
        $file = (new ReflectionMethod(MachineController::class, 'index'))->getFileName();
        $this->assertIsString($file);
        $original = filemtime($file);
        $this->assertIsInt($original);

        try {
            touch($file, $original + 60);
            clearstatcache(true, $file);

            $this->assertNotSame(
                $before,
                $generator->cacheKey('spec'),
                'Redeployed controller source must invalidate the cached description.'
            );
        } finally {
            touch($file, $original);
            clearstatcache(true, $file);
        }
    }
}
