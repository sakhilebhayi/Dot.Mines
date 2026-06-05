<?php

namespace App\Services\Integration;

use Exception;

/**
 * Kawasaki Mining Equipment Integration Service
 *
 * Handles integration with Kawasaki mining machines API
 * for real-time data synchronization and monitoring
 */
class KawasakiService extends BaseManufacturerService
{
    /**
     * Manufacturer identifier
     */
    protected string $manufacturer = 'kawasaki';

    /**
     * Test connection to Kawasaki API
     */
    public function testConnection(): bool
    {
        try {
            $response = $this->makeRequest('GET', '/health');

            return ! empty($response) && $response['success'] !== false;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }

    /**
     * Fetch machines from Kawasaki API
     *
     * @return array<mixed>
     */
    public function fetchMachines(): array
    {
        try {
            $response = $this->makeRequest('GET', '/machines');

            $machines = [];
            if (! empty($response['data']['machines'])) {
                foreach ($response['data']['machines'] as $machine) {
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
     * @return array<mixed>
     */
    public function fetchLocation(string $machineId): array
    {
        try {
            $response = $this->makeRequest('GET', "/machines/{$machineId}/location");

            return [
                'success' => true,
                'location' => $this->parseLocation($response['data'] ?? []),
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
     *
     * @return array<mixed>
     */
    public function fetchMetrics(string $machineId): array
    {
        try {
            $response = $this->makeRequest('GET', "/machines/{$machineId}/metrics");

            $metrics = [];
            if (! empty($response['data']['metrics'])) {
                foreach ($response['data']['metrics'] as $metric) {
                    $metrics[] = $this->parseMetric($metric);
                }
            }

            return [
                'success' => true,
                'metrics' => $metrics,
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
     *
     * @return array<mixed>
     */
    public function fetchAlerts(string $machineId): array
    {
        try {
            $response = $this->makeRequest('GET', "/machines/{$machineId}/alerts");

            $alerts = [];
            if (! empty($response['data']['alerts'])) {
                foreach ($response['data']['alerts'] as $alert) {
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
     * Parse machine data from Kawasaki format
     *
     * @return array<mixed>
     */
    protected function parseMachineData(array $data): array
    {
        return [
            'external_id' => $data['id'] ?? null,
            'name' => $data['name'] ?? 'Unknown Machine',
            'model' => $data['model'] ?? 'Unknown Model',
            'manufacturer' => 'Kawasaki',
            'status' => $this->parseStatus($data['status'] ?? 'unknown'),
            'location' => $this->parseLocation($data['location'] ?? []),
            'last_heartbeat' => $data['last_heartbeat'] ?? null,
            'specifications' => [
                'type' => $data['type'] ?? 'mining_machine',
                'capacity' => $data['bucket_capacity'] ?? $data['capacity'] ?? null,
                'year_manufactured' => $data['year_manufactured'] ?? null,
                'serial_number' => $data['serial_number'] ?? null,
                'model_code' => $data['model_code'] ?? null,
            ],
        ];
    }

    /**
     * Parse location data from Kawasaki format
     *
     * @return array<mixed>
     */
    protected function parseLocation(array $data): array
    {
        return [
            'latitude' => $data['latitude'] ?? $data['lat'] ?? 0,
            'longitude' => $data['longitude'] ?? $data['lng'] ?? 0,
            'altitude' => $data['elevation'] ?? $data['altitude'] ?? 0,
            'accuracy' => $data['gps_accuracy'] ?? $data['accuracy'] ?? 0,
            'timestamp' => $data['timestamp'] ?? now()->toIso8601String(),
        ];
    }

    /**
     * Parse metric data from Kawasaki format
     *
     * @return array<mixed>
     */
    protected function parseMetric(array $data): array
    {
        return [
            'type' => $data['metric_type'] ?? $data['type'] ?? 'unknown',
            'value' => $data['reading'] ?? $data['value'] ?? 0,
            'unit' => $data['unit'] ?? $data['measurement_unit'] ?? '',
            'timestamp' => $data['recorded_at'] ?? $data['timestamp'] ?? now()->toIso8601String(),
            'tags' => $data['tags'] ?? [],
        ];
    }

    /**
     * Parse alert data from Kawasaki format
     *
     * @return array<mixed>
     */
    protected function parseAlert(array $data): array
    {
        return [
            'external_id' => $data['id'] ?? null,
            'type' => $this->mapAlertType($data['alert_code'] ?? $data['type'] ?? 'unknown'),
            'priority' => $this->mapAlertPriority($data['priority_level'] ?? $data['severity'] ?? 'medium'),
            'message' => $data['description'] ?? $data['message'] ?? 'Alert from Kawasaki',
            'timestamp' => $data['alert_time'] ?? $data['timestamp'] ?? now()->toIso8601String(),
            'acknowledged' => $data['is_acknowledged'] ?? $data['acknowledged'] ?? false,
            'raw_data' => $data,
        ];
    }

    /**
     * Map Kawasaki status to standard status
     */
    protected function parseStatus(string $status): string
    {
        $statusMap = [
            'active' => 'active',
            'offline' => 'inactive',
            'standby' => 'idle',
            'operating' => 'active',
            'service' => 'maintenance',
            'down' => 'stopped',
            'fault' => 'error',
            'idle' => 'idle',
        ];

        return $statusMap[strtolower($status)] ?? 'unknown';
    }

    /**
     * Fetch machine details from Kawasaki API
     *
     * @return array<mixed>
     */
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
    public function fetchMachineLocation(string $machineId): ?array
    {
        try {
            $result = $this->fetchLocation($machineId);

            return ($result['location'] ?? null) ?? null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Fetch machine metrics
     *
     * @return array<mixed>
     */
    public function fetchMachineMetrics(string $machineId): array
    {
        try {
            $result = $this->fetchMetrics($machineId);

            return $result['metrics'] ?? [];
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Fetch machine alerts
     *
     * @return array<mixed>
     */
    public function fetchMachineAlerts(string $machineId): array
    {
        try {
            $result = $this->fetchAlerts($machineId);

            return $result['alerts'] ?? [];
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Fetch comprehensive machine data
     *
     * @return array<mixed>
     */
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
     * Get the manufacturer name
     */
    public function getManufacturer(): string
    {
        return $this->manufacturer;
    }

    /**
     * Get API error if any occurred
     */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }
}
