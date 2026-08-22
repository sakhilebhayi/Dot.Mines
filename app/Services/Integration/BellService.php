<?php

namespace App\Services\Integration;

use Carbon\CarbonInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;
use Throwable;

/**
 * Bell Equipment ISO 15143-3 (AEMP 2.0) Fleet API integration.
 *
 * Replaces an earlier version of this class that guessed at a
 * "Fleetmatic" REST/JSON API (/fleetmatic/v1/vehicles etc.) which never
 * matched any real Bell endpoint -- its own docblock admitted "Requires
 * Bell account ID and API access approval" because nobody had ever
 * actually connected it. This version is built directly against Bell's
 * published "BELL_ISO15143-3 SSO" Postman collection, which specifies:
 *
 *  - OAuth2 Resource Owner Password Credentials grant against
 *    https://sso.bellequipment.com/connect/token (client_id
 *    "ISO_Export_Service", scope "ISO_Exports", client_authenticated via
 *    client_secret) -- see getAccessToken()/fetchNewToken().
 *  - A single POST /Fleet snapshot endpoint returning every piece of
 *    equipment visible to the account, with current status inline
 *    (location, cumulative hours, fuel, DEF%, odometer, engine running).
 *  - Twelve POST /Fleet/Equipment/{id}/{Metric}/{startUTC}/{endUTC}
 *    time-series endpoints for historical drill-down per machine
 *    (locations, caution codes, cumulative totals, etc).
 *
 * Responses are XML (ISO 15143-3's wire format), parsed defensively via
 * XPath local-name() lookups so this survives whatever exact element
 * nesting/namespace Bell's live server uses -- this integration has been
 * built against the documented spec and a real client_id/scope, but has
 * not yet been confirmed against a live response (the sandbox this was
 * built in cannot make outbound requests carrying real account
 * credentials). Use the "Test Connection" button in Integration Manager
 * against a real Bell account to confirm the exact field parsing before
 * relying on it in production; every parsed field falls back to null
 * rather than guessing, and the full raw response is preserved in
 * raw_data for diagnosis if something doesn't map as expected.
 */
class BellService extends BaseManufacturerService
{
    protected string $manufacturer = 'bell';

    private ?string $username;

    private ?string $password;

    private string $clientId;

    private ?string $clientSecret;

    private string $scope;

    private string $tokenUrl;

    private readonly BellFleetParser $parser;

    /**
     * @param  array<string, mixed>  $credentials
     */
    /** @psalm-suppress PossiblyUnusedMethod -- instantiated by the container (app()/DI), which psalm cannot see */
    public function __construct(array $credentials = [])
    {
        parent::__construct($credentials);

        $this->parser = new BellFleetParser;

        $config = config('integrations.manufacturers.bell', []);

        $this->baseUrl = $credentials['base_url'] ?? $config['base_url'] ?? 'https://b-fleet03.bellequipment.com:8080';
        $this->tokenUrl = $credentials['token_url'] ?? $config['token_url'] ?? 'https://sso.bellequipment.com/connect/token';
        $this->username = $credentials['username'] ?? null;
        $this->password = $credentials['password'] ?? null;
        $this->clientId = $credentials['client_id'] ?? $config['client_id'] ?? 'ISO_Export_Service';
        $this->clientSecret = $credentials['client_secret'] ?? null;
        $this->scope = $credentials['scope'] ?? $config['scope'] ?? 'ISO_Exports';
    }

    /**
     * A successful token exchange proves the account credentials are valid;
     * a successful /Fleet call on top of that proves the data endpoint is
     * reachable and returning something parseable.
     */
    #[\Override]
    public function testConnection(): bool
    {
        try {
            $xml = $this->requestXml('/Fleet');

            return $xml !== null;
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }

    /**
     * Pull the full current-status fleet snapshot in a single call. Bell's
     * /Fleet response carries current location/hours/fuel/DEF%/odometer
     * inline per machine, so this alone is enough for a regular sync poll --
     * the 12 per-machine time-series endpoints are for historical
     * drill-down only (see fetchMachineMetrics()/fetchMachineAlerts()).
     */
    /** @return array<string, mixed> */
    #[\Override]
    public function fetchMachines(): array
    {
        // Micro-cache: location and status jobs both consume the snapshot
        // within the same scheduler window; one Bell call serves both.
        // 240s guarantees at most ONE Bell call per 5-minute scheduler
        // window regardless of how job timing aligns -- observed live
        // (2026-08-21 21:02) that Bell's limiter 405s even back-to-back
        // calls a minute apart. Still far inside Bell's own 15-minute
        // data cadence, so no consumer can observe extra staleness.
        /** @var array{success: bool, machines: list<array<string, mixed>>, count: int}|null $cached */
        $cached = Cache::get($this->fleetSnapshotCacheKey());

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $xml = $this->requestXml('/Fleet');

            if ($xml === null) {
                return [
                    'success' => false,
                    'error' => $this->lastError ?? 'No response from Bell Fleet endpoint',
                    'machines' => [],
                ];
            }

            $equipmentNodes = $this->parser->extractEquipmentNodes($xml);

            // Seed the EquipmentID->PIN map from the snapshot we already
            // hold, so the per-machine time-series calls that follow a sync
            // never need their own /Fleet round-trip to resolve IDs.
            $map = $this->parser->buildPinMap($equipmentNodes);

            if ($map !== []) {
                $this->pinByEquipmentId = $map;
                Cache::put($this->pinMapCacheKey(), $map, now()->addMinutes(15));
            }

            $machines = array_map(
                fn (SimpleXMLElement $node) => $this->parser->parseEquipmentNode($node),
                $equipmentNodes
            );

            $result = [
                'success' => true,
                'machines' => $machines,
                'count' => count($machines),
            ];

            // Only successful snapshots are cached -- a failure must be
            // retried by the next caller, never replayed for 60 seconds.
            Cache::put($this->fleetSnapshotCacheKey(), $result, now()->addSeconds(240));

            return $result;
        } catch (Throwable $e) {
            $this->logError('Failed to fetch Bell fleet snapshot', $e);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'machines' => [],
            ];
        }
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function fetchMachineDetails(string $machineId): array
    {
        $result = $this->fetchMachines();

        if (! $result['success']) {
            return [];
        }

        foreach ($result['machines'] as $machine) {
            if (($machine['external_id'] ?? null) === $machineId) {
                return $machine;
            }
        }

        return [];
    }

    /**
     * Current locations for the WHOLE fleet from the single /Fleet
     * snapshot. The per-machine Locations time-series endpoint costs one
     * API call per machine; polling jobs calling it for a 26-machine
     * fleet every few seconds is what got this server throttled by Bell
     * on 2026-08-21. The snapshot already carries every machine's latest
     * position in one call -- batch consumers must use this.
     *
     * @psalm-suppress PossiblyUnusedMethod -- reached dynamically via method_exists() from IntegrationService's batch paths
     *
     * @return list<array{manufacturer_id: string, latitude: float, longitude: float, timestamp: string, heading: null, speed: null, accuracy: null}>
     */
    public function fetchAllMachineLocations(): array
    {
        $result = $this->fetchMachines();

        if (($result['success'] ?? false) !== true) {
            return [];
        }

        $locations = [];

        /** @var list<array{external_id?: string|null, last_location?: array{latitude: float, longitude: float, timestamp: string}|null}> $machines */
        $machines = $result['machines'] ?? [];

        foreach ($machines as $machine) {
            $location = $machine['last_location'] ?? null;
            $externalId = $machine['external_id'] ?? null;

            if ($location === null || $externalId === null) {
                continue;
            }

            $locations[] = [
                'manufacturer_id' => $externalId,
                'latitude' => $location['latitude'],
                'longitude' => $location['longitude'],
                'timestamp' => $location['timestamp'],
                'heading' => null,
                'speed' => null,
                'accuracy' => null,
            ];
        }

        return $locations;
    }

    #[\Override]
    public function fetchMachineLocation(string $machineId): ?array
    {
        try {
            $readings = $this->fetchTimeSeries($machineId, 'locations', now()->subHours(2), now());

            if (empty($readings)) {
                return null;
            }

            $latest = end($readings);
            $latitude = $this->parser->toFloatOrNull($latest['attributes']['Latitude'] ?? null);
            $longitude = $this->parser->toFloatOrNull($latest['attributes']['Longitude'] ?? null);

            if (! $this->parser->isValidCoordinate($latitude, $longitude)) {
                return null;
            }

            return [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'accuracy' => null,
                'timestamp' => $latest['timestamp'],
                'heading' => $this->parser->toFloatOrNull($latest['attributes']['Heading'] ?? null),
                'speed' => $this->parser->toFloatOrNull($latest['attributes']['Speed'] ?? null),
            ];
        } catch (Throwable $e) {
            $this->logError('Failed to fetch Bell machine location', $e);

            return null;
        }
    }

    /**
     * SyncMachineMetricsJob calls this directly and does
     * `$machine->metrics()->create($metrics)` with whatever comes back --
     * same single flat MachineMetric-fillable-fields contract as the
     * 'metrics' key inside fetchMachines()'s per-machine array, not a list
     * of {type,value} readings. The /Fleet snapshot already carries current
     * status inline per machine, so this reuses that one call rather than
     * making eleven separate time-series requests for what's already there.
     */
    #[\Override]
    public function fetchMachineMetrics(string $machineId): array
    {
        try {
            $xml = $this->requestXml('/Fleet');

            if ($xml === null) {
                return [];
            }

            foreach ($this->parser->extractEquipmentNodes($xml) as $node) {
                $header = $node->xpath(".//*[local-name()='EquipmentHeader']")[0] ?? $node;

                if ($this->parser->findValue($header, ['EquipmentID']) === $machineId) {
                    /** @var array<string, mixed> */
                    return $this->parser->buildCurrentMetric($node);
                }
            }

            return [];
        } catch (Throwable $e) {
            $this->logError('Failed to fetch Bell machine metrics', $e);

            return [];
        }
    }

    /**
     * Bell's caution codes are raw manufacturer fault codes -- this
     * integration has no access to Bell's fault-code-to-description lookup
     * table, so codes are surfaced as-is rather than guessed at.
     *
     * @return list<array<string, mixed>>
     */
    #[\Override]
    public function fetchMachineAlerts(string $machineId): array
    {
        try {
            $readings = $this->fetchTimeSeries($machineId, 'cautionCodes', now()->subDay(), now());

            // Field names match what IntegrationService::syncMachineAlerts()
            // reads off each alert (title/description/type/priority/status),
            // the same shape BaseManufacturerService::parseAlerts() produces
            // for the other manufacturer services.
            return array_map(fn (array $reading) => [
                'external_id' => $machineId.'-'.$reading['timestamp'].'-'.$reading['value'],
                'title' => 'Bell caution code '.$reading['value'],
                'description' => 'Caution code '.$reading['value'].' reported by Bell ISO 15143-3 telemetry.',
                'type' => 'sensor',
                'priority' => 'medium',
                'status' => 'active',
                'timestamp' => $reading['timestamp'],
            ], $readings);
        } catch (Throwable $e) {
            $this->logError('Failed to fetch Bell machine alerts', $e);

            return [];
        }
    }

    /**
     * Production history from Bell's two cumulative production counters:
     * CumulativeLoadCount (lifetime load count) and CumulativePayloadTotals
     * (lifetime hauled mass, kilograms in Bell's own reference data). These
     * are the same documented ISO 15143-3 time-series endpoints the
     * locations/caution-codes fetches already use -- IntegrationService
     * turns consecutive cumulative readings into per-day production deltas,
     * so nothing here is estimated.
     */
    #[\Override]
    public function fetchMachineProduction(string $machineId, Carbon $start, Carbon $end): array
    {
        try {
            return [
                'success' => true,
                'load_count_readings' => $this->parser->toProductionReadings(
                    $this->fetchTimeSeries($machineId, 'loadCount', $start, $end)
                ),
                'payload_readings' => $this->parser->toProductionReadings(
                    $this->fetchTimeSeries($machineId, 'payloadTotals', $start, $end)
                ),
            ];
        } catch (Throwable $e) {
            $this->logError('Failed to fetch Bell machine production', $e);

            return [
                'success' => false,
                'load_count_readings' => [],
                'payload_readings' => [],
            ];
        }
    }

    /**
     * Bell's per-equipment time-series endpoints are addressed by the
     * machine's PIN/serial, NOT its display EquipmentID. Confirmed live
     * 2026-08-21: "/Fleet/Equipment/AEBA850EH03509112/CautionCodes/..."
     * returns 200 with ISO 15143-3 CautionMessages, while the same call
     * with the EquipmentID ("ASA  B50E#9112 " -- note the '#', doubled and
     * trailing spaces) is rejected 400 by ASP.NET path validation before
     * it ever reaches a route. Callers throughout this app hold the
     * EquipmentID (machines.manufacturer_id), so resolve it to the PIN via
     * the /Fleet snapshot; fetchMachines() seeds the map for free during a
     * sync, and other flows fall back to one cached snapshot call.
     *
     * @var array<string, string>
     */
    private array $pinByEquipmentId = [];

    private function resolveTimeSeriesId(string $equipmentId): string
    {
        $map = $this->equipmentPinMap();
        $trimmed = trim($equipmentId);

        if (isset($map[$trimmed])) {
            return $map[$trimmed];
        }

        // Already a PIN (or an ID Bell can route) -- pass through untouched.
        return $trimmed;
    }

    /**
     * @return array<string, string>
     */
    private function equipmentPinMap(): array
    {
        if ($this->pinByEquipmentId !== []) {
            return $this->pinByEquipmentId;
        }

        $cacheKey = $this->pinMapCacheKey();

        /** @var array<string, string>|null $cached */
        $cached = Cache::get($cacheKey);

        if (is_array($cached) && $cached !== []) {
            return $this->pinByEquipmentId = $cached;
        }

        $xml = $this->requestXml('/Fleet');

        if ($xml === null) {
            // Do not cache a failure -- the next call should try again.
            return [];
        }

        $map = $this->parser->buildPinMap($this->parser->extractEquipmentNodes($xml));

        if ($map !== []) {
            Cache::put($cacheKey, $map, now()->addMinutes(15));
        }

        return $this->pinByEquipmentId = $map;
    }

    private function pinMapCacheKey(): string
    {
        return 'bell_equipment_pin_map_'.md5(($this->username ?? '').'|'.($this->clientId ?? ''));
    }

    private function fleetSnapshotCacheKey(): string
    {
        return 'bell_fleet_snapshot_'.md5(($this->username ?? '').'|'.($this->clientId ?? ''));
    }

    /**
     * Calls one of the twelve /Fleet/Equipment/{id}/{Metric}/{start}/{end}
     * time-series endpoints and returns its readings ordered oldest-first.
     * Each reading's own attributes are preserved (not just a single
     * 'value') because some series -- Locations chief among them -- carry
     * more than one meaningful field per reading.
     *
     * @return list<array{timestamp: string, value: string, attributes: array<string, string>}>
     */
    private function fetchTimeSeries(string $equipmentId, string $endpointKey, CarbonInterface $start, CarbonInterface $end): array
    {
        $template = config("integrations.manufacturers.bell.supported_endpoints.{$endpointKey}");

        if (! $template) {
            return [];
        }

        // Zulu format, never toIso8601String(): its '+00:00' offset puts a
        // literal '+' in the URL path, which Bell's server rejects with 400
        // (observed live 2026-08-21 -- every time-series call failed).
        $path = strtr($template, [
            '{equipmentId}' => rawurlencode($this->resolveTimeSeriesId($equipmentId)),
            '{startDateUTC}' => $start->clone()->utc()->format('Y-m-d\TH:i:s\Z'),
            '{endDateUTC}' => $end->clone()->utc()->format('Y-m-d\TH:i:s\Z'),
        ]);

        $xml = $this->requestXml($path);

        if ($xml === null) {
            return [];
        }

        $readings = [];

        $nodes = $xml->xpath("//*[@*[local-name()='ReadingUTC'] or @*[local-name()='Timestamp'] or @*[local-name()='DateTimeUTC'] or @*[local-name()='datetime'] or @*[local-name()='DateTime']]");

        foreach (is_array($nodes) ? $nodes : [] as $node) {
            $attributes = [];
            foreach ($node->attributes() ?? [] as $name => $value) {
                $attributes[(string) $name] = (string) $value;
            }

            // Bell's cumulative production series (CumulativeLoadCount,
            // CumulativePayloadTotals) are element-style: a lowercase
            // `datetime` attribute with the value in CHILD elements
            // (<Count>, <Payload>, <PayloadUnits>) -- observed live
            // 2026-08-22. Only locations/caution codes use the
            // attribute-style shape matched above, so without folding
            // children in, production sync parsed zero readings forever.
            $children = $node->xpath('./*');
            foreach (is_array($children) ? $children : [] as $child) {
                if ($child->count() === 0) {
                    $attributes[$child->getName()] = trim((string) $child);
                }
            }

            $timestamp = $attributes['ReadingUTC'] ?? $attributes['Timestamp'] ?? $attributes['DateTimeUTC'] ?? $attributes['datetime'] ?? $attributes['DateTime'] ?? null;

            if ($timestamp === null) {
                continue;
            }

            $readings[] = [
                'timestamp' => $timestamp,
                'attributes' => $attributes,
                'value' => $attributes['Value'] ?? $attributes['Reading'] ?? $attributes['Amount'] ?? $attributes['Count'] ?? $attributes['Payload'] ?? trim((string) $node),
            ];
        }

        usort($readings, fn (array $a, array $b) => $a['timestamp'] <=> $b['timestamp']);

        return $readings;
    }

    /**
     * POSTs to a Bell ISO 15143-3 data endpoint (every endpoint in the
     * published Postman collection uses POST, including pure reads) with a
     * bearer token, retrying transient failures and refreshing the token
     * once if it's rejected as expired/invalid mid-retry.
     */
    private function requestXml(string $path): ?SimpleXMLElement
    {
        $token = $this->getAccessToken();

        if (! $token) {
            return null;
        }

        $url = rtrim($this->baseUrl, '/').'/'.ltrim($path, '/');
        $attempt = 0;
        $triedRefresh = false;

        while ($attempt < $this->retries) {
            try {
                /** @var Response $response */
                $response = Http::withToken($token)
                    ->withHeaders(['Accept' => 'application/xml'])
                    ->timeout($this->timeout)
                    ->post($url);

                if ($response->successful()) {
                    $xml = $this->parseXml($response->body());

                    // Truthiness would misclassify valid empty responses:
                    // SimpleXMLElement casts an empty element like <Fleet/>
                    // to boolean false. parseXml() returns null on a real
                    // parse failure, so only null means unparseable.
                    if ($xml !== null) {
                        return $xml;
                    }

                    $this->lastError = 'Bell API returned a response that could not be parsed as XML';
                    Log::warning('Bell integration: unparseable XML response', [
                        'url' => $url,
                        'body_preview' => substr($response->body(), 0, 500),
                    ]);

                    return null;
                }

                if ($response->status() === 401 && ! $triedRefresh) {
                    // The cached token may have just expired -- refresh once
                    // and retry immediately rather than burning a full retry
                    // cycle on a predictable failure.
                    $triedRefresh = true;
                    $this->forgetCachedToken();
                    $token = $this->getAccessToken();

                    if (! $token) {
                        return null;
                    }

                    continue;
                }

                $this->lastError = "Bell API returned status {$response->status()}: {$response->body()}";
                Log::warning('Bell integration: API error', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);

                // 4xx (other than the 401 handled above) is deterministic --
                // the same request will fail the same way. Retrying tripled
                // the call volume during the first live sync and got the
                // server IP throttled by Bell. Fail fast; only 5xx and
                // connection errors below are worth another attempt.
                if ($response->status() >= 400 && $response->status() < 500) {
                    return null;
                }
            } catch (Throwable $e) {
                $this->lastError = $e->getMessage();
                Log::warning('Bell integration: request exception', [
                    'url' => $url,
                    'attempt' => $attempt + 1,
                    'error' => $e->getMessage(),
                ]);
            }

            $attempt++;

            if ($attempt < $this->retries) {
                usleep($this->retryDelay * 1000);
            }
        }

        return null;
    }

    private function parseXml(string $body): ?SimpleXMLElement
    {
        if (trim($body) === '') {
            return null;
        }

        $previousSetting = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body, SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previousSetting);

        return $xml === false ? null : $xml;
    }

    /**
     * OAuth2 Resource Owner Password Credentials grant against Bell's SSO
     * server, cached until shortly before the token's own reported expiry
     * so a sync job doesn't re-authenticate on every request.
     */
    private function getAccessToken(): ?string
    {
        if (! $this->username || ! $this->password || ! $this->clientSecret) {
            $this->lastError = 'Bell integration is missing username, password, or client secret.';

            return null;
        }

        $cached = Cache::get($this->tokenCacheKey());

        if (is_array($cached) && ! empty($cached['access_token'])) {
            return $cached['access_token'];
        }

        return $this->fetchNewToken();
    }

    private function fetchNewToken(): ?string
    {
        try {
            $response = Http::asForm()
                ->timeout($this->timeout)
                ->post($this->tokenUrl, [
                    'grant_type' => 'password',
                    'username' => $this->username,
                    'password' => $this->password,
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'scope' => $this->scope,
                ]);

            if (! $response->successful()) {
                $this->lastError = "Bell SSO token request failed with status {$response->status()}";
                Log::warning('Bell integration: token request failed', [
                    'status' => $response->status(),
                    'body_preview' => substr($response->body(), 0, 300),
                ]);

                return null;
            }

            $data = $response->json();
            $accessToken = $data['access_token'] ?? null;

            if (! $accessToken) {
                $this->lastError = 'Bell SSO response did not include an access_token';

                return null;
            }

            $expiresIn = (int) ($data['expires_in'] ?? 300);
            // Refresh a minute early so an in-flight sync never races the
            // token's real expiry.
            $ttl = max(30, $expiresIn - 60);

            Cache::put($this->tokenCacheKey(), ['access_token' => $accessToken], now()->addSeconds($ttl));

            return $accessToken;
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();
            Log::warning('Bell integration: token request exception', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function forgetCachedToken(): void
    {
        Cache::forget($this->tokenCacheKey());
    }

    private function tokenCacheKey(): string
    {
        return 'bell_iso15143_token_'.md5($this->username.'|'.$this->clientId);
    }
}
