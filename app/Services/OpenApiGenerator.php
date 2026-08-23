<?php

namespace App\Services;

use App\Http\Middleware\ApiVersion;
use App\Http\Middleware\EnsureTokenAbility;
use App\Support\ApiPayload;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use ReflectionMethod;

/**
 * Builds the OpenAPI description of this API from the routes themselves.
 *
 * The API had 83 live endpoints and roughly two documented ones, so the only
 * documentation that survives contact with a changing codebase is
 * documentation derived from it. Everything here is read out of the app at
 * runtime: the route table supplies paths, verbs and path parameters;
 * controller docblocks supply summaries; the `$request->validate([...])`
 * rules in each action supply query and body parameters; and the required
 * token ability comes from EnsureTokenAbility itself, so documented auth and
 * enforced auth cannot disagree.
 *
 * Deliberately dependency-free: no spec-generator package, and the reference
 * UI renders server-side, so the docs page pulls nothing from a CDN.
 */
final class OpenApiGenerator
{
    /**
     * Human-readable group descriptions, keyed by URL segment. Anything not
     * listed still appears -- it just gets a generated title.
     *
     * @var array<string, string>
     */
    private const TAG_DESCRIPTIONS = [
        'machines' => 'The fleet: machine records, their telemetry metrics, live location and active alerts.',
        'geofences' => 'Zones drawn on the mine map, the machine entries/exits recorded against them, and tonnage moved.',
        'alerts' => 'Operational alerts raised against machines and mine areas, with acknowledge and resolve actions.',
        'fuel' => 'Bulk fuel tanks and every fuel movement: dispensing, deliveries, transfers and recorded losses.',
        'maintenance' => 'Machine health assessments, recurring service schedules, and work orders.',
        'reports' => 'Generated reports: request one, track its status, download the file.',
        'notifications' => 'In-app notifications for the signed-in user, with read tracking.',
        'integrations' => 'Manufacturer and telematics connections, their sync status, and the machines they discovered.',
        'assignments' => 'Which machines are assigned to which mine areas, and the assignment history.',
        'live-locations' => 'A lightweight snapshot of every machine with a known position.',
        'user' => 'The signed-in user and their current team.',
        'sync' => 'Incremental deltas since a cursor, for keeping a local cache warm without re-fetching everything.',
    ];

    /**
     * The spec, cached in production only.
     *
     * Generation reflects over every controller action, so it is worth
     * caching for real traffic -- but caching it in development would serve
     * a stale reference for an hour after a route changes, which is exactly
     * the drift this whole approach exists to prevent.
     *
     * @return array<string, mixed>
     */
    public function cached(): array
    {
        if (! app()->isProduction()) {
            return $this->generate();
        }

        /** @var array<string, mixed> */
        return Cache::remember($this->cacheKey('spec'), now()->addDay(), fn (): array => $this->generate());
    }

    /**
     * The reference the documentation page renders, cached in production only.
     *
     * @return array<string, array{description: string, operations: list<array{method: string, path: string, summary: string, permission: string, path_params: list<string>, query_params: list<string>, body_params: list<string>}>}>
     */
    public function cachedReference(): array
    {
        if (! app()->isProduction()) {
            return $this->reference();
        }

        /** @var array<string, array{description: string, operations: list<array{method: string, path: string, summary: string, permission: string, path_params: list<string>, query_params: list<string>, body_params: list<string>}>}> */
        return Cache::remember($this->cacheKey('reference'), now()->addDay(), fn (): array => $this->reference());
    }

    /**
     * A cache key that changes whenever the API it describes changes.
     *
     * Keying on time alone was a quiet way to publish a lie: a deploy that
     * renamed a parameter kept serving the previous description until the TTL
     * ran out, which is precisely the drift generating the docs exists to
     * prevent. The key now carries a fingerprint of everything the spec is
     * built from, so a changed route or a redeployed controller lands on a
     * different key and regenerates immediately -- no cache-clearing step in
     * the deploy that someone can forget to add.
     */
    public function cacheKey(string $kind): string
    {
        $parts = [];

        foreach ($this->apiRoutes() as $route) {
            $methods = ApiPayload::strings($route->methods());

            $parts[] = implode(',', $methods).' '.$route->uri().' '.$route->getActionName();
        }

        foreach ($this->sourceFiles() as $file) {
            // Deploying rewrites the file, so the timestamp moves even when a
            // rename leaves the byte count identical.
            $parts[] = $file.'@'.(string) filemtime($file);
        }

        return "api.openapi.{$kind}.".substr(hash('sha256', implode("\n", $parts)), 0, 16);
    }

    /**
     * The distinct controller files the spec is reflected out of.
     *
     * @return list<string>
     */
    private function sourceFiles(): array
    {
        $files = [];

        foreach ($this->apiRoutes() as $route) {
            $file = $this->actionReflection($route)?->getFileName();

            if (is_string($file) && ! in_array($file, $files, true) && file_exists($file)) {
                $files[] = $file;
            }
        }

        sort($files);

        return $files;
    }

    /**
     * The full OpenAPI 3.0.3 document.
     *
     * @return array<string, mixed>
     */
    public function generate(): array
    {
        $paths = [];

        foreach ($this->apiRoutes() as $route) {
            $path = '/'.ltrim($route->uri(), '/');
            $openApiPath = (string) preg_replace('/\{(\w+)\??\}/', '{$1}', $path);

            /** @psalm-suppress MixedAssignment */
            foreach ($route->methods() as $method) {
                $method = (string) $method;

                if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
                    continue;
                }

                $paths[$openApiPath][strtolower($method)] = $this->operation($route, $method);
            }
        }

        ksort($paths);

        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => config('app.name').' API',
                'version' => '1.0.0',
                'description' => $this->apiDescription(),
            ],
            // The host only. Paths below already carry the full `/api/v1/...`,
            // so a server URL ending in `/api` made every generated client
            // call `/api/api/machines`.
            'servers' => [
                ['url' => rtrim(ApiPayload::str(config('app.url')), '/'), 'description' => 'This installation'],
            ],
            'tags' => $this->tags($paths),
            'security' => [['bearerAuth' => []]],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'description' => 'A personal access token from Settings > API Tokens, sent as '
                            .'`Authorization: Bearer <token>`. The token\'s permissions are enforced per request: '
                            .'read for GET, create/update for POST, update for PUT, delete for DELETE.',
                    ],
                ],
                'schemas' => $this->schemas(),
            ],
            'paths' => $paths,
        ];
    }

    /**
     * The same endpoint inventory, shaped for rendering in the docs page.
     *
     * Built from the routes directly rather than by re-parsing the spec, so
     * the values stay typed and the permission comes straight from the
     * middleware instead of being read back out of prose.
     *
     * @return array<string, array{description: string, operations: list<array{method: string, path: string, summary: string, permission: string, path_params: list<string>, query_params: list<string>, body_params: list<string>}>}>
     */
    public function reference(): array
    {
        $grouped = [];

        foreach ($this->apiRoutes() as $route) {
            $tag = $this->tagFor($route);
            $doc = $this->docblockFor($route);
            $path = '/'.ltrim($route->uri(), '/');

            /** @psalm-suppress MixedAssignment */
            foreach ($route->methods() as $method) {
                $method = (string) $method;

                if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
                    continue;
                }

                $parameters = $this->parameters($route, $method);
                $body = $this->requestBody($route, $method);

                $queryParams = [];
                $pathParams = [];

                foreach ($parameters as $parameter) {
                    if ($parameter['in'] === 'query') {
                        $queryParams[] = (string) $parameter['name'];
                    } else {
                        $pathParams[] = (string) $parameter['name'];
                    }
                }

                $grouped[$tag] ??= [
                    'description' => self::TAG_DESCRIPTIONS[$tag] ?? ucfirst(str_replace('-', ' ', $tag)).' endpoints.',
                    'operations' => [],
                ];

                $grouped[$tag]['operations'][] = [
                    'method' => $method,
                    'path' => $path,
                    'summary' => $doc['summary'] !== '' ? $doc['summary'] : $this->fallbackSummary($route, $method),
                    'permission' => implode(' or ', EnsureTokenAbility::abilitiesFor($method)),
                    'path_params' => $pathParams,
                    'query_params' => $queryParams,
                    'body_params' => $this->bodyFieldNames($body),
                ];
            }
        }

        ksort($grouped);

        foreach ($grouped as $tag => $group) {
            usort(
                $grouped[$tag]['operations'],
                static fn (array $a, array $b): int => [$a['path'], $a['method']] <=> [$b['path'], $b['method']]
            );
        }

        return $grouped;
    }

    /**
     * Every route of the current API version, in a stable order.
     *
     * Deliberately the versioned paths only. The same endpoints are also
     * served bare at `/api/...` for older integrations, but documenting both
     * would double the spec and give every operation a duplicate
     * operationId -- so the description shows the version to build against
     * and says plainly that the bare paths exist and what they resolve to.
     *
     * @return list<RoutingRoute>
     */
    public function apiRoutes(): array
    {
        $routes = [];
        $prefix = 'api/'.ApiVersion::CURRENT.'/';

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), $prefix)) {
                continue;
            }

            $routes[] = $route;
        }

        usort($routes, static fn (RoutingRoute $a, RoutingRoute $b): int => strcmp($a->uri(), $b->uri()));

        return $routes;
    }

    /**
     * @return array<string, mixed>
     */
    private function operation(RoutingRoute $route, string $method): array
    {
        $tag = $this->tagFor($route);
        $doc = $this->docblockFor($route);
        $abilities = EnsureTokenAbility::abilitiesFor($method);

        $operation = [
            'tags' => [$tag],
            'summary' => $doc['summary'] !== '' ? $doc['summary'] : $this->fallbackSummary($route, $method),
            'description' => trim($doc['description']."\n\nRequires a token with the "
                .'`'.implode('` or `', $abilities).'` permission.'),
            'operationId' => $this->operationId($route, $method),
            'parameters' => $this->parameters($route, $method),
            'responses' => $this->responses($method),
        ];

        if ($doc['description'] === '') {
            $operation['description'] = 'Requires a token with the `'.implode('` or `', $abilities).'` permission.';
        }

        $body = $this->requestBody($route, $method);

        if ($body !== null) {
            $operation['requestBody'] = $body;
        }

        return $operation;
    }

    /**
     * Path parameters from the route, plus query parameters read out of the
     * action's validation rules.
     *
     * @return list<array<string, mixed>>
     */
    private function parameters(RoutingRoute $route, string $method): array
    {
        $parameters = [];

        /** @psalm-suppress MixedAssignment */
        foreach ($route->parameterNames() as $name) {
            $name = (string) $name;
            $parameters[] = [
                'name' => $name,
                'in' => 'path',
                'required' => true,
                'description' => 'Identifier of the '.str_replace('_', ' ', $name).'.',
                'schema' => ['type' => str_ends_with($name, 'id') || $name === 'machine' ? 'integer' : 'string'],
            ];
        }

        // Body verbs describe their fields under requestBody instead.
        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            return $parameters;
        }

        foreach ($this->validationRules($route) as $field => $rules) {
            $parameters[] = [
                'name' => $field,
                'in' => 'query',
                'required' => in_array('required', $rules, true),
                'schema' => $this->schemaForRules($rules),
            ];
        }

        return $parameters;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function requestBody(RoutingRoute $route, string $method): ?array
    {
        if (! in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            return null;
        }

        $rules = $this->validationRules($route);

        if ($rules === []) {
            return null;
        }

        $properties = [];
        $required = [];

        foreach ($rules as $field => $fieldRules) {
            $properties[$field] = $this->schemaForRules($fieldRules);

            if (in_array('required', $fieldRules, true)) {
                $required[] = $field;
            }
        }

        $schema = ['type' => 'object', 'properties' => $properties];

        if ($required !== []) {
            $schema['required'] = $required;
        }

        return [
            'required' => $required !== [],
            'content' => ['application/json' => ['schema' => $schema]],
        ];
    }

    /**
     * Field names from a generated requestBody, if it has any.
     *
     * @param  array<string, mixed>|null  $body
     * @return list<string>
     */
    private function bodyFieldNames(?array $body): array
    {
        $properties = data_get($body, 'content.application/json.schema.properties');

        if (! is_array($properties)) {
            return [];
        }

        return array_map(static fn (int|string $key): string => (string) $key, array_keys($properties));
    }

    /**
     * Turn a Laravel rule list into an OpenAPI schema fragment.
     *
     * @param  list<string>  $rules
     * @return array<string, mixed>
     */
    private function schemaForRules(array $rules): array
    {
        $schema = ['type' => 'string'];

        foreach ($rules as $rule) {
            if ($rule === 'integer') {
                $schema['type'] = 'integer';
            } elseif ($rule === 'numeric') {
                $schema['type'] = 'number';
            } elseif ($rule === 'boolean') {
                $schema['type'] = 'boolean';
            } elseif ($rule === 'array') {
                $schema['type'] = 'array';
                $schema['items'] = ['type' => 'string'];
            } elseif ($rule === 'date') {
                $schema['type'] = 'string';
                $schema['format'] = 'date';
            } elseif (str_starts_with($rule, 'in:')) {
                $schema['enum'] = explode(',', substr($rule, 3));
            } elseif (str_starts_with($rule, 'min:')) {
                $key = ($schema['type'] ?? 'string') === 'string' ? 'minLength' : 'minimum';
                $schema[$key] = (int) substr($rule, 4);
            } elseif (str_starts_with($rule, 'max:')) {
                $key = ($schema['type'] ?? 'string') === 'string' ? 'maxLength' : 'maximum';
                $schema[$key] = (int) substr($rule, 4);
            } elseif (str_starts_with($rule, 'exists:')) {
                $schema['description'] = 'Must reference an existing record in your team.';
            }
        }

        return $schema;
    }

    /**
     * Read the validation rules out of an action's source.
     *
     * Rules are runtime values inside the method body, so they cannot be
     * reflected -- they are parsed from the source of the first
     * `validate([...])` / `Validator::make(..., [...])` call in the action.
     *
     * @return array<string, list<string>>
     */
    private function validationRules(RoutingRoute $route): array
    {
        $source = $this->actionSource($route);

        if ($source === null) {
            return [];
        }

        if (! preg_match('/(?:->validate\(|Validator::make\([^,]+,\s*)\[(.*?)\]\s*\)/s', $source, $match)) {
            return [];
        }

        $rules = [];

        // 'field' => 'rule|rule' and 'field' => ['rule', 'rule']
        preg_match_all("/'([a-zA-Z0-9_.*]+)'\s*=>\s*(?:'([^']*)'|\[([^\]]*)\])/", $match[1], $pairs, PREG_SET_ORDER);

        foreach ($pairs as $pair) {
            $field = $pair[1];

            if (str_contains($field, '.') || str_contains($field, '*')) {
                continue; // nested/array-item rules add noise to the reference
            }

            if (($pair[2] ?? '') !== '') {
                $rules[$field] = explode('|', $pair[2]);
            } else {
                preg_match_all("/'([^']+)'/", $pair[3] ?? '', $items);
                $rules[$field] = $items[1];
            }
        }

        return $rules;
    }

    /**
     * Summary (first line) and description (remaining prose) from the
     * action's docblock. Lines that merely restate the route are dropped --
     * "GET /api/machines" is already in the path.
     *
     * @return array{summary: string, description: string}
     */
    private function docblockFor(RoutingRoute $route): array
    {
        $method = $this->actionReflection($route);
        $doc = $method?->getDocComment();

        if ($doc === null || $doc === false) {
            return ['summary' => '', 'description' => ''];
        }

        $lines = [];

        foreach (explode("\n", $doc) as $line) {
            $line = trim((string) preg_replace('/^\s*\/?\*+\/?/', '', $line));

            if ($line === '' || str_starts_with($line, '@')) {
                continue;
            }

            if (preg_match('#^(GET|POST|PUT|PATCH|DELETE)\s+/#i', $line)) {
                continue;
            }

            if (stripos($line, 'query params:') === 0) {
                continue; // parameters are generated properly below
            }

            $lines[] = $line;
        }

        if ($lines === []) {
            return ['summary' => '', 'description' => ''];
        }

        $summary = array_shift($lines);

        return ['summary' => $summary, 'description' => implode(' ', $lines)];
    }

    private function actionReflection(RoutingRoute $route): ?ReflectionMethod
    {
        $action = $route->getActionName();

        // Route closures have no docblock or source to reflect on.
        if ($action === 'Closure') {
            return null;
        }

        if (! str_contains($action, '@')) {
            // Single-action controllers are invoked via __invoke.
            if (class_exists($action) && method_exists($action, '__invoke')) {
                return new ReflectionMethod($action, '__invoke');
            }

            return null;
        }

        $parts = explode('@', $action, 2);

        if (count($parts) !== 2) {
            return null;
        }

        [$class, $method] = $parts;

        if (! class_exists($class) || ! method_exists($class, $method)) {
            return null;
        }

        return new ReflectionMethod($class, $method);
    }

    private function actionSource(RoutingRoute $route): ?string
    {
        $reflection = $this->actionReflection($route);
        $file = $reflection?->getFileName();

        if ($reflection === null || $file === false || $file === null) {
            return null;
        }

        $lines = file($file);

        if ($lines === false) {
            return null;
        }

        $start = $reflection->getStartLine() - 1;
        $length = $reflection->getEndLine() - $start;

        return implode('', array_slice($lines, $start, $length));
    }

    /**
     * The group an endpoint belongs to: the first real segment after
     * `api/{version}/`, so the version itself never becomes a group.
     */
    private function tagFor(RoutingRoute $route): string
    {
        return $this->tagForPath('/'.ltrim($route->uri(), '/'));
    }

    private function tagForPath(string $path): string
    {
        $segments = explode('/', ltrim($path, '/'));

        // ['api', 'v1', 'machines', ...]
        return $segments[2] ?? 'api';
    }

    private function operationId(RoutingRoute $route, string $method): string
    {
        $action = $route->getActionName();

        $parts = explode('@', $action, 2);

        if (count($parts) === 2) {
            [$class, $actionMethod] = $parts;
            $segments = explode('\\', $class);
            $short = str_replace('Controller', '', end($segments));

            return lcfirst($short).ucfirst($actionMethod);
        }

        return strtolower($method).str_replace(['/', '{', '}', '-'], '', ucwords($route->uri(), '/-'));
    }

    private function fallbackSummary(RoutingRoute $route, string $method): string
    {
        $verb = match ($method) {
            'POST' => 'Create',
            'PUT', 'PATCH' => 'Update',
            'DELETE' => 'Delete',
            default => 'Get',
        };

        return $verb.' '.str_replace('api/', '', $route->uri());
    }

    /**
     * @param  array<string, mixed>  $paths
     * @return list<array<string, string>>
     */
    private function tags(array $paths): array
    {
        $names = [];

        foreach (array_keys($paths) as $path) {
            $names[] = $this->tagForPath($path);
        }

        $names = array_values(array_unique($names));
        sort($names);

        return array_map(static fn (string $name): array => [
            'name' => $name,
            'description' => self::TAG_DESCRIPTIONS[$name] ?? ucfirst(str_replace('-', ' ', $name)).' endpoints.',
        ], $names);
    }

    /**
     * Standard responses. Every endpoint shares these shapes -- one list
     * envelope, one validation-error shape, one message shape.
     *
     * @return array<array-key, array<string, mixed>>
     */
    private function responses(string $method): array
    {
        $responses = [
            '200' => [
                'description' => 'Success.',
                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ListEnvelope']]],
            ],
            '401' => [
                'description' => 'Missing or invalid token.',
                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]],
            ],
            '403' => [
                'description' => 'The token lacks the required permission, or the record belongs to another team.',
                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]],
            ],
            '404' => [
                'description' => 'No such record.',
                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]],
            ],
            '429' => [
                'description' => 'Rate limit exceeded (60 requests per minute).',
                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]],
            ],
        ];

        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $responses['422'] = [
                'description' => 'The submitted data failed validation.',
                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ValidationError']]],
            ];
        }

        return $responses;
    }

    /**
     * @return array<string, mixed>
     */
    private function schemas(): array
    {
        return [
            'ListEnvelope' => [
                'type' => 'object',
                'description' => 'Every endpoint that returns a list uses this envelope. Rows are always under '
                    .'`data`; paging is always under `meta` and `links`.',
                'properties' => [
                    'data' => ['type' => 'array', 'items' => ['type' => 'object']],
                    'links' => [
                        'type' => 'object',
                        'properties' => [
                            'first' => ['type' => 'string', 'nullable' => true],
                            'last' => ['type' => 'string', 'nullable' => true],
                            'prev' => ['type' => 'string', 'nullable' => true],
                            'next' => ['type' => 'string', 'nullable' => true],
                        ],
                    ],
                    'meta' => [
                        'type' => 'object',
                        'properties' => [
                            'current_page' => ['type' => 'integer'],
                            'from' => ['type' => 'integer', 'nullable' => true],
                            'to' => ['type' => 'integer', 'nullable' => true],
                            'per_page' => ['type' => 'integer'],
                            'last_page' => ['type' => 'integer'],
                            'total' => ['type' => 'integer'],
                        ],
                    ],
                ],
            ],
            'Error' => [
                'type' => 'object',
                'properties' => ['message' => ['type' => 'string']],
            ],
            'ValidationError' => [
                'type' => 'object',
                'properties' => [
                    'message' => ['type' => 'string'],
                    'errors' => [
                        'type' => 'object',
                        'description' => 'Field name to the list of problems with it.',
                        'additionalProperties' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                ],
            ],
        ];
    }

    private function apiDescription(): string
    {
        $current = ApiVersion::CURRENT;
        $pinned = ApiVersion::PINNED_FOR_UNVERSIONED;

        return implode("\n\n", [
            'Fleet, fuel, maintenance and production data for your mine, over HTTP.',
            "**Versioning.** Build against `/api/{$current}/...` -- the paths documented here. The same "
                .'endpoints are also served without a version at `/api/...` for integrations written before '
                ."versions existed; those are pinned to `{$pinned}` permanently and will not change under you "
                .'when a newer version ships. Every response carries `X-API-Version`, and a response to an '
                .'unversioned path also carries a `Link: <...>; rel="successor-version"` header naming the '
                .'versioned URL for that endpoint. A new version means a new path -- nothing you already call '
                .'changes shape.',
            '**Authentication.** Create a token under Settings > API Tokens and send it as '
                .'`Authorization: Bearer <token>`. Permissions are enforced per request: a read-only token '
                .'cannot modify anything.',
            '**Tenancy.** Every request is scoped to the token owner\'s current team. You will never see '
                .'another team\'s records, and you do not pass a team id.',
            '**Lists.** All list endpoints return `{data, links, meta}` and accept `page` and `per_page`.',
            '**Field names are stable.** Responses are an explicit set of fields, not a dump of the '
                .'database, so internal columns are never returned and a schema change will not alter your payload.',
            '**Parameter names.** One vocabulary across the API: `start_date`/`end_date` bound a time range, '
                .'and filters use the bare field name (`status`, `type`). The older spellings '
                .'`date_from`/`date_to` and `filter_status`/`filter_type` are still accepted so existing '
                .'integrations keep working, but they are deprecated -- prefer the names documented here. If you '
                .'send both, the documented name wins.',
            '**Rate limit.** 60 requests per minute per user.',
        ]);
    }
}
