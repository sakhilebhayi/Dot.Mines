<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\EnsureTokenAbility;
use App\Services\OpenApiGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The API description is generated from the route table, not written by hand
 * -- the API previously had 83 live endpoints and roughly two documented
 * ones, because hand-written docs drift the moment a route is added.
 *
 * These tests are the ratchet: add an endpoint and it is documented
 * automatically, but if it somehow is not, this fails.
 */
class OpenApiSpecTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_api_route_appears_in_the_spec(): void
    {
        $spec = app(OpenApiGenerator::class)->generate();

        $missing = [];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/') || $route->getName() === 'api.openapi') {
                continue;
            }

            $path = '/'.$route->uri();

            foreach ($route->methods() as $method) {
                if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
                    continue;
                }

                if (! isset($spec['paths'][$path][strtolower($method)])) {
                    $missing[] = "{$method} {$path}";
                }
            }
        }

        $this->assertSame([], $missing, 'Every API route must appear in the generated spec.');
    }

    public function test_the_spec_is_publicly_reachable_and_valid_openapi(): void
    {
        $response = $this->getJson('/api/openapi.json');

        $response->assertOk();
        $response->assertJsonStructure([
            'openapi',
            'info' => ['title', 'version', 'description'],
            'servers',
            'tags',
            'components' => ['securitySchemes' => ['bearerAuth'], 'schemas' => ['ListEnvelope', 'Error', 'ValidationError']],
            'paths',
        ]);

        $this->assertSame('3.0.3', $response->json('openapi'));
        $this->assertNotEmpty($response->json('paths'), 'The spec must describe at least one path.');
    }

    public function test_documented_permissions_match_what_the_middleware_enforces(): void
    {
        $spec = app(OpenApiGenerator::class)->generate();

        foreach ($spec['paths'] as $path => $operations) {
            foreach ($operations as $method => $operation) {
                $expected = EnsureTokenAbility::abilitiesFor($method);
                $description = $operation['description'];

                foreach ($expected as $ability) {
                    $this->assertStringContainsString(
                        "`{$ability}`",
                        $description,
                        "{$method} {$path} must document the {$ability} permission the middleware requires."
                    );
                }
            }
        }
    }

    public function test_operations_carry_real_summaries_and_parameters(): void
    {
        $spec = app(OpenApiGenerator::class)->generate();

        // Summaries come from controller docblocks.
        $this->assertSame(
            'List all machines for current team',
            $spec['paths']['/api/machines']['get']['summary']
        );

        // Query parameters come from the action's validation rules, including
        // the allowed values behind an `in:` rule.
        $sort = collect($spec['paths']['/api/machines']['get']['parameters'])->firstWhere('name', 'sort');
        $this->assertNotNull($sort, 'Query parameters must be derived from the validation rules.');
        $this->assertSame(['name', 'machine_type', 'status', 'created_at'], $sort['schema']['enum']);

        // Write endpoints describe their body, including which fields are required.
        $body = $spec['paths']['/api/machines']['post']['requestBody']['content']['application/json']['schema'];
        $this->assertContains('name', $body['required']);
        $this->assertArrayHasKey('machine_type', $body['properties']);
    }

    public function test_every_operation_is_grouped_and_identified(): void
    {
        $spec = app(OpenApiGenerator::class)->generate();
        $tagNames = array_column($spec['tags'], 'name');
        $operationIds = [];

        foreach ($spec['paths'] as $path => $operations) {
            foreach ($operations as $method => $operation) {
                $this->assertNotEmpty($operation['summary'], "{$method} {$path} needs a summary.");
                $this->assertContains($operation['tags'][0], $tagNames, "{$method} {$path} has an undeclared tag.");
                $operationIds[] = $operation['operationId'];
            }
        }

        $duplicates = array_keys(array_filter(array_count_values($operationIds), static fn (int $n): bool => $n > 1));
        $this->assertSame([], $duplicates, 'operationId must be unique -- client generators key off it.');
    }
}
