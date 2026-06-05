<?php

namespace App\Services\Integration;

use App\Contracts\ManufacturerServiceInterface;
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
     * Initialize the service with API credentials
     */
    public function __construct(array $credentials = [])
    {
        $this->baseUrl = $credentials['base_url'] ?? '';
        $this->apiKey = $credentials['api_key'] ?? '';
        $this->apiSecret = $credentials['api_secret'] ?? '';
    }

    /**
     * Make HTTP request to manufacturer API with retry logic
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
     * Get authentication headers for API requests
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
    protected function parseMachineData(array $rawData): array
    {
        return [
            'external_id' => $rawData['id'] ?? null,
            'manufacturer' => $this->manufacturer,
            'model' => $rawData['model'] ?? 'Unknown',
            'serial_number' => $rawData['serial_number'] ?? null,
            'status' => $this->parseStatus($rawData['status'] ?? 'unknown'),
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
     * Parse metrics from API response
     */
    protected function parseMetrics(array $rawData): array
    {
        return [
            'timestamp' => $rawData['timestamp'] ?? now(),
            'engine_rpm' => $rawData['engine_rpm'] ?? null,
            'engine_temp' => $rawData['engine_temp'] ?? null,
            'fuel_level' => $rawData['fuel_level'] ?? null,
            'fuel_consumption' => $rawData['fuel_consumption'] ?? null,
            'hydraulic_pressure' => $rawData['hydraulic_pressure'] ?? null,
            'oil_pressure' => $rawData['oil_pressure'] ?? null,
            'coolant_temp' => $rawData['coolant_temp'] ?? null,
            'battery_voltage' => $rawData['battery_voltage'] ?? null,
            'operating_hours' => $rawData['operating_hours'] ?? null,
            'load_weight' => $rawData['load_weight'] ?? null,
            'raw_data' => $rawData,
        ];
    }

    /**
     * Parse alerts from API response
     */
    protected function parseAlerts(array $alerts): array
    {
        return array_map(function ($alert) {
            return [
                'external_id' => $alert['id'] ?? null,
                'title' => $alert['title'] ?? 'Alert',
                'description' => $alert['description'] ?? '',
                'type' => $this->mapAlertType($alert['type'] ?? 'sensor'),
                'priority' => $this->mapAlertPriority($alert['severity'] ?? 'medium'),
                'status' => $alert['status'] ?? 'new',
                'timestamp' => $alert['timestamp'] ?? now(),
            ];
        }, $alerts);
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
     * Get manufacturer name
     */
    public function getManufacturer(): string
    {
        return $this->manufacturer;
    }

    /**
     * Get last error message
     */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Test the connection to the manufacturer API
     * Concrete classes should override this
     */
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
    public function fetchMachineLocation(string $machineId): ?array
    {
        // Default implementation - concrete classes should override
        return null;
    }

    /**
     * Fetch machine metrics/diagnostics
     * Concrete classes should override this
     *
     * @return array<mixed>
     */
    public function fetchMachineMetrics(string $machineId): array
    {
        // Default implementation - concrete classes should override
        return [];
    }

    /**
     * Fetch machine alerts/faults
     * Concrete classes should override this
     *
     * @return array<mixed>
     */
    public function fetchMachineAlerts(string $machineId): array
    {
        // Default implementation - concrete classes should override
        return [];
    }

    /**
     * Fetch all data for a machine (comprehensive sync)
     * Concrete classes should override this
     *
     * @return array<string, mixed>
     */
    public function fetchMachineData(string $machineId): array
    {
        // Default implementation - concrete classes should override
        return [];
    }

    /**
     * Make HTTP request to manufacturer API (alias for request())
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    protected function makeRequest(string $method, string $endpoint, array $data = [], array $headers = []): array
    {
        return $this->request($method, $endpoint, $data, $headers);
    }

    /**
     * Log an integration error
     */
    protected function logError(string $message, \Throwable $exception): void
    {
        $this->lastError = $exception->getMessage();
        Log::error("Integration Error [{$this->manufacturer}]: {$message}", [
            'manufacturer' => $this->manufacturer,
            'exception' => $exception->getMessage(),
        ]);
    }

    /**
     * Log integration activity
     *
     * @param  array<string, mixed>  $details
     */
    protected function logActivity(string $action, array $details = []): void
    {
        Log::info("Integration Activity: {$action}", array_merge([
            'manufacturer' => $this->manufacturer,
            'timestamp' => now(),
        ], $details));
    }
}
