<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\ApiVersion;
use App\Models\Machine;
use App\Models\MineArea;
use App\Models\Team;
use App\Models\User;
use App\Services\OpenApiGenerator;
use App\Services\TeamRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The API is versioned at /api/v1, and the paths people already integrated
 * against keep working unchanged.
 *
 * Two properties matter more than the rest, and both are easy to break by
 * accident later:
 *
 * 1. The bare paths are PINNED to v1. They are not "latest". Repointing them
 *    when v2 ships would break every client that never asked to upgrade, on
 *    deploy day, with no error message.
 * 2. Both spellings enforce the SAME middleware. An alias registered without
 *    auth:sanctum or ensure_team is an open door to another team's data.
 */
class ApiVersioningTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Endpoints of the current version, keyed by the path with the version
     * stripped, so each can be matched against its bare twin.
     *
     * @return array<string, RoutingRoute>
     */
    private function versionedRoutes(): array
    {
        $prefix = 'api/'.ApiVersion::CURRENT.'/';
        $routes = [];

        foreach (Route::getRoutes() as $route) {
            if (str_starts_with($route->uri(), $prefix)) {
                $routes[implode(',', $route->methods()).' api/'.substr($route->uri(), strlen($prefix))] = $route;
            }
        }

        return $routes;
    }

    /**
     * @return array<string, RoutingRoute>
     */
    private function unversionedRoutes(): array
    {
        $routes = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            if (! str_starts_with($uri, 'api/')
                || str_starts_with($uri, 'api/'.ApiVersion::CURRENT.'/')
                || $route->getName() === 'api.openapi') {
                continue;
            }

            $routes[implode(',', $route->methods()).' '.$uri] = $route;
        }

        return $routes;
    }

    public function test_every_endpoint_is_reachable_at_both_spellings(): void
    {
        $versioned = $this->versionedRoutes();
        $unversioned = $this->unversionedRoutes();

        $this->assertNotEmpty($versioned, 'The versioned API must be registered.');

        $this->assertSame(
            [],
            array_keys(array_diff_key($versioned, $unversioned)),
            'A versioned endpoint with no bare twin breaks clients that never sent a version.'
        );

        $this->assertSame(
            [],
            array_keys(array_diff_key($unversioned, $versioned)),
            'A bare endpoint with no versioned twin is undocumented -- the spec only describes versioned paths.'
        );
    }

    public function test_both_spellings_run_the_same_controller_action(): void
    {
        $unversioned = $this->unversionedRoutes();

        foreach ($this->versionedRoutes() as $key => $route) {
            $this->assertSame(
                $route->getActionName(),
                $unversioned[$key]->getActionName(),
                "{$key} resolves to a different action depending on the spelling."
            );
        }
    }

    public function test_both_spellings_enforce_the_same_middleware(): void
    {
        $unversioned = $this->unversionedRoutes();

        foreach ($this->versionedRoutes() as $key => $route) {
            $versionedStack = $route->gatherMiddleware();
            $bareStack = $unversioned[$key]->gatherMiddleware();

            sort($versionedStack);
            sort($bareStack);

            // A bare path missing auth:sanctum or ensure_team would serve
            // another team's data to an unauthenticated caller.
            $this->assertSame(
                $versionedStack,
                $bareStack,
                "{$key} is protected differently depending on the spelling."
            );
        }
    }

    public function test_the_bare_paths_are_pinned_and_not_an_alias_for_latest(): void
    {
        // Separate constants on purpose: when CURRENT moves to v2, this one
        // must not follow it. If you are changing this test to make them
        // track each other, read routes/api.php first.
        $this->assertSame(
            'v1',
            ApiVersion::PINNED_FOR_UNVERSIONED,
            'The unversioned paths are pinned to v1 permanently.'
        );

        $this->assertFileExists(
            base_path('routes/api_'.ApiVersion::PINNED_FOR_UNVERSIONED.'.php'),
            'The pinned version must still have a route file to serve.'
        );
    }

    public function test_both_spellings_return_the_same_payload(): void
    {
        $user = $this->apiUser();
        $this->seedMachine($user);

        $versioned = $this->getJson('/api/v1/machines')->assertOk();
        $bare = $this->getJson('/api/machines')->assertOk();

        $this->assertSame($versioned->json('data'), $bare->json('data'));
    }

    public function test_every_response_names_the_version_that_answered(): void
    {
        $user = $this->apiUser();
        $this->seedMachine($user);

        $this->getJson('/api/v1/machines')->assertOk()->assertHeader('X-API-Version', 'v1');
        $this->getJson('/api/machines')->assertOk()->assertHeader('X-API-Version', 'v1');
    }

    public function test_a_bare_response_points_at_its_versioned_url(): void
    {
        $user = $this->apiUser();
        $this->seedMachine($user);

        // A client on the bare path can discover where to migrate without a
        // human reading the docs.
        $link = $this->getJson('/api/machines')->assertOk()->headers->get('Link');

        $this->assertNotNull($link);
        $this->assertStringContainsString('/api/v1/machines', $link);
        $this->assertStringContainsString('rel="successor-version"', $link);

        // The versioned path is already current; nothing to succeed it.
        $this->assertNull($this->getJson('/api/v1/machines')->assertOk()->headers->get('Link'));
    }

    public function test_the_versioned_paths_require_authentication_too(): void
    {
        // Adding a second spelling must not add a second way in.
        $this->getJson('/api/v1/machines')->assertUnauthorized();
        $this->getJson('/api/machines')->assertUnauthorized();
    }

    public function test_the_docs_describe_the_versioned_paths(): void
    {
        $reference = app(OpenApiGenerator::class)->reference();

        $this->assertArrayHasKey('machines', $reference, 'The version segment must not become a group.');
        $this->assertArrayNotHasKey('v1', $reference);

        $paths = collect($reference['machines']['operations'])->pluck('path');
        $this->assertTrue($paths->every(fn (string $path): bool => str_starts_with($path, '/api/v1/')));
    }

    private function apiUser(): User
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);
        TeamRoleProvisioner::assignRole($user, $team, 'admin');
        Sanctum::actingAs($user->fresh(), ['*']);

        return $user->fresh();
    }

    private function seedMachine(User $user): void
    {
        $area = MineArea::factory()->create(['team_id' => $user->current_team_id]);

        Machine::factory()->create([
            'team_id' => $user->current_team_id,
            'mine_area_id' => $area->id,
            'name' => 'VERSIONED-DIGGER',
        ]);
    }
}
