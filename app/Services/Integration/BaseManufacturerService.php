<?php

namespace App\Services\Integration;

use App\Contracts\ManufacturerServiceInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class BaseManufacturerService implements ManufacturerServiceInterface
{
    protected string $manufacturer;

    protected string $baseUrl;

    protected string $apiKey;

    protected string $apiSecret;

    protected ?string $lastError = null;

    protected int $timeout = 30;

    protected int $retries = 3;

    protected int $retryDelay = 1000; // milliseconds

    /**
     * Tests exercising retry behaviour need to skip the real inter-attempt
     * sleep; production code never changes this.
     *
     * @psalm-suppress PossiblyUnusedMethod -- called from tests only
     */
    public function setRetryDelay(int $milliseconds): void
    {
        $this->retryDelay = $milliseconds;
    }

    /**
     * Initialize the service with API credentials
     *
     * @param  array<string, mixed>  $credentials
     */
    public function __construct(array $credentials = [])
    {
        $this->baseUrl = $credentials['base_url'] ?? '';
        $this->apiKey = $credentials['api_key'] ?? '';
        $this->apiSecret = $credentials['api_secret'] ?? '';
    }

    /**
     * Make HTTP request to manufacturer API with retry logic
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $headers
     * @return array<string, mixed>
     */
    protected function request(
        string $method,
        string $endpoint,
        array $data = [],
        array $headers = []
    ): array {
        $url = rtrim($this->baseUrl, '/').'/'.ltrim($endpoint, '/');

        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->retries) {
            try {
                $response = Http::timeout($this->timeout)
                    ->withHeaders($this->getAuthHeaders($headers))
                    ->retry($attempt, $this->retryDelay)
                    ->{strtolower($method)}($url, $data);

                if ($response->successful()) {
                    return [
                        'success' => true,
                        'data' => $response->json(),
                        'status' => $response->status(),
                    ];
                } else {
                    $this->lastError = "API returned status {$response->status()}: {$response->body()}";
                    Log::warning("Integration API Error: {$this->lastError}", [
                        'manufacturer' => $this->manufacturer,
                        'endpoint' => $endpoint,
                    ]);
                }
            } catch (\Exception $e) {
                $lastException = $e;
                $this->lastError = $e->getMessage();

                Log::warning("Integration API Exception: {$this->lastError}", [
                    'manufacturer' => $this->manufacturer,
                    'endpoint' => $endpoint,
                    'attempt' => $attempt + 1,
                ]);

                if ($attempt < $this->retries - 1) {
                    usleep($this->retryDelay * 1000);
                }
            }

            $attempt++;
        }

        return [
            'success' => false,
            'error' => $this->lastError ?? 'Unknown error occurred',
            'exception' => $lastException,
        ];
    }

    /**
     * Alias for request(). 17 of the 24 manufacturer subclasses call this
     * name (consistently, across every one of them) while this class only
     * ever defined request() -- meaning testConnection()/fetchMachines()/etc
     * on Bell, CAT, C-Track, Doosan, Epiroc, Hitachi, Hyundai, JCB,
     * Kawasaki, Kobelco, Komatsu, Kubota, Liebherr, Roundebult, Sany, Volvo,
     * and XCMG threw an uncaught "call to undefined method" fatal error
     * (PHP's Error, not Exception, so their own catch (Exception $e) blocks
     * never caught it) the instant a user actually tested or synced one of
     * those integrations.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $headers
     * @return array<string, mixed>
     */
    protected function makeRequest(
        string $method,
        string $endpoint,
        array $data = [],
        array $headers = []
    ): array {
        return $this->request($method, $endpoint, $data, $headers);
    }

    /**
     * Alias used by the same 17 subclasses as makeRequest() above, for the
     * same reason -- this class never defined it.
     */
    protected function logError(string $message, \Throwable $e): void
    {
        $this->lastError = $e->getMessage();

        Log::error("Integration Error: {$message}", [
            'manufacturer' => $this->manufacturer,
            'error' => $e->getMessage(),
        ]);
    }

    /**
     * Get authentication headers for API requests
     *
     * @param  array<string, mixed>  $additionalHeaders
     * @return array<string, mixed>
     */
    protected function getAuthHeaders(array $additionalHeaders = []): array
    {
        return array_merge([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.$this->apiKey,
        ], $additionalHeaders);
    }

    /**
     * Parse machine data from API response to standard format
     */
    /** @param array<string, mixed> $rawData
     * @return array<string, mixed> */
    protected function parseMachineData(array $rawData): array
    {
        return [
            'external_id' => $rawData['id'] ?? null,
            'manufacturer' => $this->manufacturer,
            'model' => $rawData['model'] ?? 'Unknown',
            'serial_number' => $rawData['serial_number'] ?? null,
            'status' => $this->parseStatus(is_string($rawData['status'] ?? null) ? $rawData['status'] : 'unknown'),
            'last_location' => [
                'latitude' => $rawData['latitude'] ?? null,
                'longitude' => $rawData['longitude'] ?? null,
                'timestamp' => $rawData['location_timestamp'] ?? now(),
            ],
            'metrics' => $rawData['metrics'] ?? [],
            'alerts' => $rawData['alerts'] ?? [],
        ];
    }

    /**
     * Parse location data from API response
     */
    /** @param array<string, mixed> $rawData
     * @return array<string, mixed>|null */
    protected function parseLocation(array $rawData): ?array
    {
        if (empty($rawData['latitude']) || empty($rawData['longitude'])) {
            return null;
        }

        return [
            'latitude' => (float) $rawData['latitude'],
            'longitude' => (float) $rawData['longitude'],
            'accuracy' => $rawData['accuracy'] ?? null,
            'timestamp' => $rawData['timestamp'] ?? now(),
            'heading' => $rawData['heading'] ?? null,
            'speed' => $rawData['speed'] ?? null,
        ];
    }

    /**
     * Parse metrics from API response into MachineMetric's own fillable
     * column names -- this used to use 'timestamp'/'engine_temp'/
     * 'fuel_consumption'/'coolant_temp', none of which are real MachineMetric
     * columns ('recorded_at'/'engine_temperature'/'fuel_consumption_rate'/
     * 'coolant_temperature' are), so every one of those four fields was
     * silently dropped by mass assignment on every single metrics sync, for
     * every manufacturer service that calls this method.
     */
    /** @param array<string, mixed> $rawData
     * @return array<string, mixed> */
    protected function parseMetrics(array $rawData): array
    {
        return [
            'recorded_at' => $rawData['timestamp'] ?? now(),
            'engine_rpm' => $rawData['engine_rpm'] ?? null,
            'engine_temperature' => $rawData['engine_temp'] ?? null,
            'fuel_level' => $rawData['fuel_level'] ?? null,
            'fuel_consumption_rate' => $rawData['fuel_consumption'] ?? null,
            'oil_pressure' => $rawData['oil_pressure'] ?? null,
            'coolant_temperature' => $rawData['coolant_temp'] ?? null,
            'battery_voltage' => $rawData['battery_voltage'] ?? null,
            'operating_hours' => $rawData['operating_hours'] ?? null,
            'load_weight' => $rawData['load_weight'] ?? null,
            'raw_data' => $rawData,
        ];
    }

    /**
     * Several manufacturer services call parseMetrics() 2-3 times (once per
     * API endpoint -- e.g. performance/production/maintenance) and combine
     * the results with a plain array_merge(). Since parseMetrics() always
     * returns the exact same set of keys, a plain array_merge() means
     * whichever call came *last* silently overwrites every field from the
     * earlier calls, even where the earlier source had a real value and the
     * later one had null -- in practice, only the final endpoint's data ever
     * survived. This keeps the first non-null value found for each field
     * instead, and unions raw_data rather than letting later ones replace it.
     *
     * @param  array<string, mixed>  $metricSets
     * @return array<string, mixed>
     */
    protected function mergeMetricsPreferNonNull(array ...$metricSets): array
    {
        $merged = [];
        $rawData = [];

        foreach ($metricSets as $index => $set) {
            foreach ($set as $key => $value) {
                if ($key === 'raw_data') {
                    $rawData[] = $value;

                    continue;
                }

                if (! array_key_exists($key, $merged) || $merged[$key] === null) {
                    $merged[$key] = $value;
                }
            }
        }

        $merged['raw_data'] = $rawData;

        return $merged;
    }

    /**
     * SyncMachineMetricsJob and IntegrationService::syncMachineMetrics()
     * both mass-assign whatever fetchMachineMetrics() returns directly onto
     * a MachineMetric -- they expect one flat array of its own fillable
     * columns, not a list of {type, value, unit, timestamp} readings (the
     * shape parseMetric()/the manual $metrics[] = [...] pattern builds).
     * Passing that list straight through, as several manufacturer services
     * did, meant the create() call still succeeded but silently produced an
     * almost-empty row (just team_id/machine_id) every time, since none of
     * 'type'/'value'/'unit' are real column names.
     *
     * This doesn't attempt to map each manufacturer's own invented reading
     * "type" strings (fuel_used, engineHours, productivity, ...) onto real
     * MachineMetric columns -- those names were never derived from a real,
     * confirmed OEM API response (see BellService for what that looks like
     * once a real spec actually exists), so guessing a mapping here would
     * just trade one kind of fabrication for another. Every reading is kept
     * as-is in raw_data instead, so nothing already being fetched is lost,
     * and recorded_at is set to the latest reading's own timestamp -- the
     * row is now structurally valid and inspectable rather than silently
     * empty, ready for real field mapping once a real response is seen.
     *
     * @param  list<array<string, mixed>>  $readings
     * @return array<string, mixed>
     */
    protected function normalizeMetricsForStorage(array $readings): array
    {
        if (empty($readings)) {
            return [];
        }

        $latest = collect($readings)
            ->pluck('timestamp')
            ->filter()
            ->map(function ($timestamp) {
                try {
                    return $timestamp instanceof \DateTimeInterface
                        ? Carbon::instance($timestamp)
                        : Carbon::parse((string) $timestamp);
                } catch (\Throwable) {
                    return null;
                }
            })
            ->filter()
            ->sortDesc()
            ->first();

        return [
            'recorded_at' => $latest ?? now(),
            'raw_data' => $readings,
        ];
    }

    /**
     * Parse alerts from API response
     */
    /**
     * Normalise ONE raw provider alert into the standard shape. Ten
     * manufacturer services called $this->parseAlert() without any
     * definition in their hierarchy -- a guaranteed fatal on the first
     * real fetchAlerts() against those providers (refactor R5 find).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function parseAlert(array $data): array
    {
        return [
            'external_id' => $data['id'] ?? $data['alert_id'] ?? null,
            'type' => $this->mapAlertType((string) ($data['type'] ?? $data['code'] ?? 'unknown')),
            'priority' => $this->mapAlertPriority((string) ($data['severity'] ?? $data['priority'] ?? 'medium')),
            'message' => $data['message'] ?? $data['description'] ?? "Alert from {$this->manufacturer}",
            // Default missing statuses to 'active', never the legacy 'new'
            // (not in the alerts.status enum -- the bug ManufacturerAlerts-
            // ShapeTest pins). This lived in the deleted, never-called
            // parseAlerts(); the live single-alert normaliser owns it now.
            'status' => $data['status'] ?? 'active',
            'timestamp' => $data['timestamp'] ?? $data['created_at'] ?? now()->toIso8601String(),
            'acknowledged' => (bool) ($data['acknowledged'] ?? false),
            'raw_data' => $data,
        ];
    }

    /**
     * Map machine status from manufacturer format to standard
     */
    protected function parseStatus(string $status): string
    {
        $statusMap = [
            'active' => 'active',
            'running' => 'active',
            'in_use' => 'active',
            'idle' => 'idle',
            'parked' => 'idle',
            'offline' => 'offline',
            'maintenance' => 'maintenance',
            'service' => 'maintenance',
        ];

        return $statusMap[strtolower($status)] ?? 'unknown';
    }

    /**
     * Map alert type from manufacturer format to standard
     */
    protected function mapAlertType(string $type): string
    {
        $typeMap = [
            'temperature' => 'temperature',
            'fuel' => 'fuel',
            'maintenance' => 'maintenance',
            'sensor' => 'sensor',
            'geofence' => 'geofence',
            'downtime' => 'downtime',
            'error' => 'sensor',
            'warning' => 'sensor',
        ];

        return $typeMap[strtolower($type)] ?? 'sensor';
    }

    /**
     * Map alert priority from manufacturer format to standard
     */
    protected function mapAlertPriority(string $severity): string
    {
        $priorityMap = [
            'critical' => 'critical',
            'error' => 'high',
            'warning' => 'medium',
            'info' => 'low',
            'high' => 'high',
            'medium' => 'medium',
            'low' => 'low',
        ];

        return $priorityMap[strtolower($severity)] ?? 'medium';
    }

    /**
     * Get last error message
     */
    #[\Override]
    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Test the connection to the manufacturer API
     * Concrete classes should override this
     */
    #[\Override]
    public function testConnection(): bool
    {
        // Default implementation - concrete classes should override
        return false;
    }

    /**
     * Fetch all machines from the manufacturer API
     * Concrete classes should override this
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function fetchMachines(): array
    {
        // Default implementation - concrete classes should override
        return [];
    }

    /**
     * Fetch machine details from the manufacturer API
     * Concrete classes should override this
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function fetchMachineDetails(string $machineId): array
    {
        // Default implementation - concrete classes should override
        return [];
    }

    /**
     * Fetch real-time location for a machine
     * Concrete classes should override this
     *
     * @return array<string, mixed>|null
     */
    #[\Override]
    public function fetchMachineLocation(string $machineId): ?array
    {
        // Default implementation - concrete classes should override
        return null;
    }

    /**
     * Fetch machine metrics/diagnostics
     * Concrete classes should override this
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function fetchMachineMetrics(string $machineId): array
    {
        // Default implementation - concrete classes should override
        return [];
    }

    /**
     * Fetch machine alerts/faults
     * Concrete classes should override this
     *
     * @return list<array<string, mixed>>
     */
    #[\Override]
    public function fetchMachineAlerts(string $machineId): array
    {
        // Default implementation - concrete classes should override
        return [];
    }

    /**
     * Fetch cumulative production counter readings for a machine.
     * success=false means "this provider has no production data source",
     * so IntegrationService derives nothing rather than fabricating
     * production out of fleet metadata. Providers with a real production
     * endpoint (Bell's ISO 15143-3 CumulativeLoadCount /
     * CumulativePayloadTotals time series) override this.
     *
     * @return array{success: bool, load_count_readings: list<array{timestamp: string, value: float, units: ?string}>, payload_readings: list<array{timestamp: string, value: float, units: ?string}>}
     */
    #[\Override]
    public function fetchMachineProduction(string $machineId, Carbon $start, Carbon $end): array
    {
        return [
            'success' => false,
            'load_count_readings' => [],
            'payload_readings' => [],
        ];
    }
}
