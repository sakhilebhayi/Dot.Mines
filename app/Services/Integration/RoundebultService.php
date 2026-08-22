<?php

namespace App\Services\Integration;

use Exception;

/**
 * Roundebult Fleet Management Integration Service
 *
 * Contact Roundebult for API access
 * South African fleet management provider
 */
class RoundebultService extends BaseManufacturerService
{
    /**
     * Manufacturer identifier
     */
    protected string $manufacturer = 'roundebult';

    /**
     * Test connection to Roundebult API
     */
    #[\Override]
    public function testConnection(): bool
    {
        try {
            $response = $this->makeRequest('GET', '/api/v1/machines', [
                'query' => ['limit' => 1],
            ]);

            return ! empty($response) && $response['success'] !== false;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }

    /**
     * Fetch machines from Roundebult API
     */
    /** @return array<string, mixed> */
    #[\Override]
    public function fetchMachines(): array
    {
        try {
            $response = $this->makeRequest('GET', '/api/v1/machines');

            $machines = [];
            if (! empty($response['data']['machines'])) {
                $rows28 = self::rowsOf(data_get($response, 'data.machines'));
                foreach ($rows28 as $machine) {
                    $machines[] = $this->parseMachineData($machine);
                }
            }

            return [
                'success' => true,
                'machines' => $machines,
                'count' => count($machines),
            ];
        } catch (Exception $e) {
            $this->logError('Failed to fetch machines', $e);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'machines' => [],
            ];
        }
    }

    /**
     * Fetch location data for a machine
     *
     * @return array<string, mixed>
     */
    public function fetchLocation(string $machineId): array
    {
        try {
            $response = $this->makeRequest('GET', "/api/v1/machines/{$machineId}/location");

            return [
                'success' => true,
                'location' => $this->parseLocation(self::payloadArray($response['data'] ?? null)),
            ];
        } catch (Exception $e) {
            $this->logError('Failed to fetch location', $e);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Fetch metrics for a machine
     */
    /** @return array<string, mixed> */
    public function fetchMetrics(string $machineId): array
    {
        try {
            $metrics = $this->makeRequest('GET', "/api/v1/machines/{$machineId}/metrics");
            $operations = $this->makeRequest('GET', "/api/v1/machines/{$machineId}/operations");

            // array_merge() would let whichever source is listed last
            // silently overwrite every field from the earlier one, since
            // parseMetrics() always returns the same set of keys -- see
            // mergeMetricsPreferNonNull().
            $allMetrics = $this->mergeMetricsPreferNonNull(
                $this->parseMetrics(self::payloadArray($metrics['data'] ?? null)),
                $this->parseMetrics(self::payloadArray($operations['data'] ?? null))
            );

            return [
                'success' => true,
                'metrics' => $allMetrics,
            ];
        } catch (Exception $e) {
            $this->logError('Failed to fetch metrics', $e);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'metrics' => [],
            ];
        }
    }

    /**
     * Fetch alerts for a machine
     */
    /** @return array<string, mixed> */
    public function fetchAlerts(string $machineId): array
    {
        try {
            $response = $this->makeRequest('GET', "/api/v1/machines/{$machineId}/alerts");

            $alerts = [];
            if (! empty($response['data']['alerts'])) {
                $rows29 = self::rowsOf(data_get($response, 'data.alerts'));
                foreach ($rows29 as $alert) {
                    $alerts[] = $this->parseAlert($alert);
                }
            }

            return [
                'success' => true,
                'alerts' => $alerts,
            ];
        } catch (Exception $e) {
            $this->logError('Failed to fetch alerts', $e);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'alerts' => [],
            ];
        }
    }

    /**
     * Parse machine data from Roundebult format
     */
    /** @param array<string, mixed> $data
     * @return array<string, mixed> */
    #[\Override]
    protected function parseMachineData(array $data): array
    {
        return [
            'external_id' => $data['id'] ?? null,
            'name' => $data['name'] ?? 'Unknown Machine',
            'model' => $data['model'] ?? 'Unknown Model',
            'manufacturer' => 'Roundebult',
            'status' => $this->parseStatus(is_string($data['status'] ?? null) ? $data['status'] : 'unknown'),
            'location' => $this->parseLocation(self::payloadArray($data['location'] ?? null)),
            'last_heartbeat' => $data['last_heartbeat'] ?? null,
            'specifications' => [
                'type' => $data['type'] ?? 'mining_machine',
                'capacity' => $data['capacity'] ?? null,
                'year_manufactured' => $data['year_manufactured'] ?? null,
                'serial_number' => $data['serial_number'] ?? null,
            ],
        ];
    }

    /**
     * Parse location data from Roundebult format
     *
     * @return array<string, mixed>
     */
    #[\Override]
    protected function parseLocation(array $data): array
    {
        return [
            'latitude' => $data['lat'] ?? $data['latitude'] ?? 0,
            'longitude' => $data['lng'] ?? $data['longitude'] ?? 0,
            'altitude' => $data['altitude'] ?? 0,
            'accuracy' => $data['accuracy'] ?? 0,
            'timestamp' => $data['timestamp'] ?? now()->toIso8601String(),
        ];
    }

    /**
     * Parse alert data from Roundebult format
     */
    #[\Override]
    protected function parseAlert(array $data): array
    {
        return [
            // Missing statuses default to 'active', never the legacy 'new'
            // (not in the alerts.status enum) -- same invariant as the Base
            // normaliser, pinned by ManufacturerAlertsShapeTest.
            'status' => $data['status'] ?? 'active',
            'external_id' => $data['id'] ?? null,
            'type' => $this->mapAlertType(is_string($data['alert_type'] ?? null) ? $data['alert_type'] : 'unknown'),
            'priority' => $this->mapAlertPriority(is_string($data['severity'] ?? null) ? $data['severity'] : 'medium'),
            'message' => $data['message'] ?? 'Alert from Roundebult',
            'timestamp' => $data['timestamp'] ?? now()->toIso8601String(),
            'acknowledged' => $data['acknowledged'] ?? false,
            'raw_data' => $data,
        ];
    }

    /**
     * Map Roundebult status to standard status
     */
    #[\Override]
    protected function parseStatus(string $status): string
    {
        $statusMap = [
            'online' => 'active',
            'offline' => 'inactive',
            'idle' => 'idle',
            'working' => 'active',
            'maintenance' => 'maintenance',
            'stopped' => 'stopped',
            'error' => 'error',
        ];

        return $statusMap[strtolower($status)] ?? 'unknown';
    }

    /**
     * Fetch machine details from Roundebult API
     */
    /** @return array<string, mixed> */
    #[\Override]
    public function fetchMachineDetails(string $machineId): array
    {
        // Return location and metrics as a composite detail view
        $location = $this->fetchLocation($machineId);

        return [
            'location' => $location['location'] ?? [],
            'success' => $location['success'] ?? false,
        ];
    }

    /**
     * Fetch machine location
     */
    #[\Override]
    public function fetchMachineLocation(string $machineId): ?array
    {
        try {
            $result = $this->fetchLocation($machineId);

            return is_array($result['location'] ?? null) ? self::payloadArray($result['location']) : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Fetch machine metrics
     */
    #[\Override]
    public function fetchMachineMetrics(string $machineId): array
    {
        try {
            $result = $this->fetchMetrics($machineId);

            return self::payloadArray($result['metrics'] ?? null);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Fetch machine alerts
     */
    #[\Override]
    public function fetchMachineAlerts(string $machineId): array
    {
        try {
            $result = $this->fetchAlerts($machineId);

            return self::rowsOf($result['alerts'] ?? null);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get API error if any occurred
     */
    #[\Override]
    public function getLastError(): ?string
    {
        return $this->lastError;
    }
}
