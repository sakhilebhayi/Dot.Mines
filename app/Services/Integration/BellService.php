<?php

namespace App\Services\Integration;

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

    private ?string $clientId;

    private ?string $clientSecret;

    private string $scope;

    private string $tokenUrl;

    public function __construct(array $credentials = [])
    {
        parent::__construct($credentials);

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
    public function fetchMachines(): array
    {
        try {
            $xml = $this->requestXml('/Fleet');

            if ($xml === null) {
                return [
                    'success' => false,
                    'error' => $this->lastError ?? 'No response from Bell Fleet endpoint',
                    'machines' => [],
                ];
            }

            $machines = array_map(
                fn (SimpleXMLElement $node) => $this->parseEquipmentNode($node),
                $this->extractEquipmentNodes($xml)
            );

            return [
                'success' => true,
                'machines' => $machines,
                'count' => count($machines),
            ];
        } catch (Throwable $e) {
            $this->logError('Failed to fetch Bell fleet snapshot', $e);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'machines' => [],
            ];
        }
    }

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

    public function fetchMachineLocation(string $machineId): ?array
    {
        try {
            $readings = $this->fetchTimeSeries($machineId, 'locations', now()->subHours(2), now());

            if (empty($readings)) {
                return null;
            }

            $latest = end($readings);
            $latitude = $this->toFloatOrNull($latest['attributes']['Latitude'] ?? null);
            $longitude = $this->toFloatOrNull($latest['attributes']['Longitude'] ?? null);

            if (! $this->isValidCoordinate($latitude, $longitude)) {
                return null;
            }

            return [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'accuracy' => null,
                'timestamp' => $latest['timestamp'],
                'heading' => $this->toFloatOrNull($latest['attributes']['Heading'] ?? null),
                'speed' => $this->toFloatOrNull($latest['attributes']['Speed'] ?? null),
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
    public function fetchMachineMetrics(string $machineId): array
    {
        try {
            $xml = $this->requestXml('/Fleet');

            if ($xml === null) {
                return [];
            }

            foreach ($this->extractEquipmentNodes($xml) as $node) {
                $header = $node->xpath(".//*[local-name()='EquipmentHeader']")[0] ?? $node;

                if ($this->findValue($header, ['EquipmentID']) === $machineId) {
                    return $this->buildCurrentMetric($node);
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
     */
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

    public function fetchMachineData(string $machineId): array
    {
        return [
            'details' => $this->fetchMachineDetails($machineId),
            'location' => $this->fetchMachineLocation($machineId),
            'metrics' => $this->fetchMachineMetrics($machineId),
            'alerts' => $this->fetchMachineAlerts($machineId),
        ];
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
    public function fetchMachineProduction(string $machineId, Carbon $start, Carbon $end): array
    {
        try {
            return [
                'success' => true,
                'load_count_readings' => $this->toProductionReadings(
                    $this->fetchTimeSeries($machineId, 'loadCount', $start, $end)
                ),
                'payload_readings' => $this->toProductionReadings(
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
     * @param  list<array{timestamp: string, value: string, attributes: array<string, string>}>  $readings
     * @return list<array{timestamp: string, value: float, units: ?string}>
     */
    private function toProductionReadings(array $readings): array
    {
        $parsed = [];

        foreach ($readings as $reading) {
            $value = $this->toFloatOrNull($reading['value'] ?? null);

            if ($value === null) {
                continue;
            }

            $parsed[] = [
                'timestamp' => $reading['timestamp'],
                'value' => $value,
                'units' => $reading['attributes']['PayloadUnits']
                    ?? $reading['attributes']['Units']
                    ?? null,
            ];
        }

        return $parsed;
    }

    /**
     * Parse a single <Equipment> node from the /Fleet snapshot into this
     * app's standard machine-sync shape (see IntegrationService::syncMachine()).
     */
    private function parseEquipmentNode(SimpleXMLElement $equipment): array
    {
        $header = $equipment->xpath(".//*[local-name()='EquipmentHeader']")[0] ?? $equipment;

        $externalId = $this->findValue($header, ['EquipmentID']);
        $latitude = $this->toFloatOrNull($this->findValue($equipment, ['Latitude']));
        $longitude = $this->toFloatOrNull($this->findValue($equipment, ['Longitude']));
        $telemetryDate = $this->findValue($equipment, ['TelemetryDate']) ?? now()->toIso8601String();
        $engineRunning = $this->parseEngineRunning($equipment);

        return [
            'external_id' => $externalId,
            'name' => $externalId ?? 'Unknown Bell Machine',
            'model' => $this->findValue($header, ['Model']),
            'manufacturer' => 'Bell',
            'serial_number' => $this->findValue($header, ['SerialNumber']),
            'status' => $engineRunning === null
                ? 'unknown'
                : $this->parseStatus($engineRunning ? 'running' : 'idle'),
            'last_location' => $this->isValidCoordinate($latitude, $longitude) ? [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'timestamp' => $telemetryDate,
            ] : null,
            'specifications' => [
                'type' => 'haul_truck',
                'pin' => $this->findValue($header, ['PIN']),
            ],
            // IntegrationService::syncMachineMetrics() feeds this directly
            // into `new MachineMetric($metrics)` -- a single flat array of
            // MachineMetric's own fillable columns, not a list of readings.
            'metrics' => $this->buildCurrentMetric($equipment),
            'alerts' => [],
        ];
    }

    /**
     * The current-status shape shared by fetchMachines() (per equipment
     * node in the /Fleet snapshot) and fetchMachineMetrics() (looked up by
     * ID from that same snapshot) -- a single flat array of MachineMetric's
     * fillable columns. 'recorded_at' (not 'timestamp') is the fillable
     * column name.
     */
    private function buildCurrentMetric(SimpleXMLElement $equipment): array
    {
        $latitude = $this->toFloatOrNull($this->findValue($equipment, ['Latitude']));
        $longitude = $this->toFloatOrNull($this->findValue($equipment, ['Longitude']));
        $telemetryDate = $this->findValue($equipment, ['TelemetryDate']) ?? now()->toIso8601String();
        $engineRunning = $this->parseEngineRunning($equipment);

        $fuelRemaining = $this->toFloatOrNull($this->findValue($equipment, ['FuelRemainingPercent', 'FuelRemainingRatio']));
        // ISO 15143-3's own element is a *ratio* (0-1); Bell's flattened
        // example uses a percent (0-100) under a differently named field.
        // Normalise defensively either way rather than assuming which one a
        // given response actually used.
        if ($fuelRemaining !== null && $fuelRemaining <= 1) {
            $fuelRemaining *= 100;
        }

        return [
            'recorded_at' => $telemetryDate,
            'latitude' => $this->isValidLatitude($latitude) ? $latitude : null,
            'longitude' => $this->isValidLongitude($longitude) ? $longitude : null,
            'fuel_level' => $this->isValidPercent($fuelRemaining) ? $fuelRemaining : null,
            'operating_hours' => $this->toFloatOrNull($this->findValue($equipment, ['OperatingHours'])),
            'idle_hours' => $this->toFloatOrNull($this->findValue($equipment, ['IdleHours'])),
            // ISO's "Payload" here is a cumulative lifetime total, not an
            // instantaneous load -- kept in raw_data instead of the
            // load_weight column, which the rest of this app treats as
            // "what's on the machine right now".
            'load_weight' => null,
            'raw_data' => [
                'load_count' => $this->toFloatOrNull($this->findValue($equipment, ['LoadCount'])),
                'cumulative_payload' => $this->toFloatOrNull($this->findValue($equipment, ['Payload'])),
                'payload_units' => $this->findValue($equipment, ['PayloadUnits']),
                'def_percent' => $this->toFloatOrNull($this->findValue($equipment, ['DEFPercent'])),
                'odometer' => $this->toFloatOrNull($this->findValue($equipment, ['Odometer'])),
                'odometer_units' => $this->findValue($equipment, ['OdometerUnits']),
                'fuel_consumed_cumulative' => $this->toFloatOrNull($this->findValue($equipment, ['FuelConsumed'])),
                'fuel_units' => $this->findValue($equipment, ['FuelUnits']),
                'engine_running' => $engineRunning,
            ],
        ];
    }

    private function parseEngineRunning(SimpleXMLElement $equipment): ?bool
    {
        $raw = $this->findValue($equipment, ['EngineRunning']);

        return $raw === null ? null : filter_var($raw, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @return list<SimpleXMLElement>
     */
    private function extractEquipmentNodes(SimpleXMLElement $fleet): array
    {
        return $fleet->xpath("//*[local-name()='Equipment']") ?: [];
    }

    /**
     * Looks for each candidate name first as an attribute on $node, then
     * anywhere in $node's subtree as either an element's own text or a
     * Value/Reading/Amount attribute on it. Searching by local-name() (not
     * a fixed path) means this keeps working regardless of exactly how
     * Bell nests these fields or which XML namespace it uses.
     *
     * @param  list<string>  $candidateNames
     */
    private function findValue(SimpleXMLElement $node, array $candidateNames): ?string
    {
        foreach ($candidateNames as $name) {
            if (isset($node[$name]) && (string) $node[$name] !== '') {
                return (string) $node[$name];
            }
        }

        foreach ($candidateNames as $name) {
            $matches = $node->xpath(".//*[local-name()='{$name}']");

            if (empty($matches)) {
                continue;
            }

            $match = $matches[0];

            foreach (['Value', 'Reading', 'Amount'] as $attr) {
                if (isset($match[$attr]) && (string) $match[$attr] !== '') {
                    return (string) $match[$attr];
                }
            }

            if ((string) $match !== '') {
                return (string) $match;
            }
        }

        return null;
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
    private function fetchTimeSeries(string $equipmentId, string $endpointKey, Carbon $start, Carbon $end): array
    {
        $template = config("integrations.manufacturers.bell.supported_endpoints.{$endpointKey}");

        if (! $template) {
            return [];
        }

        $path = strtr($template, [
            '{equipmentId}' => rawurlencode($equipmentId),
            '{startDateUTC}' => $start->clone()->utc()->toIso8601String(),
            '{endDateUTC}' => $end->clone()->utc()->toIso8601String(),
        ]);

        $xml = $this->requestXml($path);

        if ($xml === null) {
            return [];
        }

        $readings = [];

        foreach ($xml->xpath("//*[@*[local-name()='ReadingUTC'] or @*[local-name()='Timestamp'] or @*[local-name()='DateTimeUTC']]") as $node) {
            $attributes = [];
            foreach ($node->attributes() as $name => $value) {
                $attributes[(string) $name] = (string) $value;
            }

            $timestamp = $attributes['ReadingUTC'] ?? $attributes['Timestamp'] ?? $attributes['DateTimeUTC'] ?? null;

            if ($timestamp === null) {
                continue;
            }

            $readings[] = [
                'timestamp' => $timestamp,
                'attributes' => $attributes,
                'value' => $attributes['Value'] ?? $attributes['Reading'] ?? $attributes['Amount'] ?? (string) $node,
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

    private function isValidLatitude(?float $value): bool
    {
        return $value !== null && $value >= -90 && $value <= 90;
    }

    private function isValidLongitude(?float $value): bool
    {
        return $value !== null && $value >= -180 && $value <= 180;
    }

    private function isValidPercent(?float $value): bool
    {
        return $value !== null && $value >= 0 && $value <= 100;
    }

    private function isValidCoordinate(?float $latitude, ?float $longitude): bool
    {
        return $this->isValidLatitude($latitude) && $this->isValidLongitude($longitude);
    }

    private function toFloatOrNull(?string $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }
}
