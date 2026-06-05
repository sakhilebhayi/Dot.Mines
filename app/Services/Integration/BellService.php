<?php

namespace App\Services\Integration;

use Exception;

/**
 * Bell Fleetmatic Integration Service
 *
 * Handles integration with Bell Fleetmatic API
 * Requires Bell account ID and API access approval
 * Contact: Bell Equipment for API access
 */
class BellService extends BaseManufacturerService
{
    /**
     * Manufacturer identifier
     */
    protected string $manufacturer = 'bell';

    /**
     * Test connection to Bell Fleetmatic API
     */
    public function testConnection(): bool
    {
        try {
            // Test with vehicles endpoint
            $response = $this->makeRequest('GET', '/fleetmatic/v1/vehicles');

            if ($response && isset($response['success'])) {
                return (bool) $response['success'];
            }

            return true;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }

    /**
     * Fetch vehicles from Bell Fleetmatic API
     *
     * @return array<mixed>
     */
    public function fetchMachines(): array
    {
        try {
            $response = $this->makeRequest('GET', '/fleetmatic/v1/vehicles');

            $machines = [];
            if (! empty($response['vehicles'])) {
                foreach ($response['vehicles'] as $vehicle) {
                    $machines[] = $this->parseMachineData($vehicle);
                }
            }

            return [
                'success' => true,
                'machines' => $machines,
                'count' => count($machines),
            ];
        } catch (Exception $e) {
            $this->logError('Failed to fetch vehicles', $e);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'machines' => [],
            ];
        }
    }

    /**
     * Fetch location data for vehicle
     */
    public function fetchMachineLocation(string $machineId): ?array
    {
        try {
            $response = $this->makeRequest('GET', "/fleetmatic/v1/vehicles/{$machineId}/location");

            return $this->parseLocation($response);
        } catch (Exception $e) {
            $this->logError('Failed to fetch location', $e);

            return null;
        }
    }

    /**
     * Fetch operational metrics for vehicle
     *
     * @return array<mixed>
     */
    public function fetchMachineMetrics(string $machineId): array
    {
        try {
            // Fetch multiple metric types from Fleetmatic
            $telemetry = $this->makeRequest('GET', "/fleetmatic/v1/vehicles/{$machineId}/telemetry");
            $fuel = $this->makeRequest('GET', "/fleetmatic/v1/vehicles/{$machineId}/fuel");
            $engine = $this->makeRequest('GET', "/fleetmatic/v1/vehicles/{$machineId}/engine");
            $trips = $this->makeRequest('GET', "/fleetmatic/v1/vehicles/{$machineId}/trips");

            $metrics = [];

            // Parse telemetry
            if (! empty($telemetry['data'])) {
                foreach ($telemetry['data'] as $metric) {
                    $metrics[] = $this->parseMetric($metric);
                }
            }

            // Add fuel data
            if (! empty($fuel['fuelLevel'])) {
                $metrics[] = [
                    'type' => 'fuel_level',
                    'value' => $fuel['fuelLevel'],
                    'unit' => 'liters',
                    'timestamp' => $fuel['timestamp'] ?? now(),
                ];
            }

            // Add engine data
            if (! empty($engine['engineData'])) {
                $metrics[] = [
                    'type' => 'engine_status',
                    'value' => $engine['engineData'],
                    'timestamp' => $engine['timestamp'] ?? now(),
                ];
            }

            // Add trip data
            if (! empty($trips['recentTrips'])) {
                $metrics[] = [
                    'type' => 'trips',
                    'value' => count($trips['recentTrips']),
                    'timestamp' => now(),
                ];
            }

            return $metrics;
        } catch (Exception $e) {
            $this->logError('Failed to fetch metrics', $e);

            return [];
        }
    }

    /**
     * Fetch alerts for vehicle
     *
     * @return array<mixed>
     */
    public function fetchMachineAlerts(string $machineId): array
    {
        try {
            $response = $this->makeRequest('GET', "/fleetmatic/v1/vehicles/{$machineId}/alerts");

            $alerts = [];
            if (! empty($response['alerts'])) {
                foreach ($response['alerts'] as $alert) {
                    $alerts[] = $this->parseAlert($alert);
                }
            }

            return $alerts;
        } catch (Exception $e) {
            $this->logError('Failed to fetch alerts', $e);

            return [];
        }
    }

    /**
     * Fetch machine details from Bell API
     *
     * @return array<mixed>
     */
    public function fetchMachineDetails(string $machineId): array
    {
        try {
            $response = $this->makeRequest('GET', "/fleetmatic/v1/vehicles/{$machineId}");

            return $response;
        } catch (Exception $e) {
            $this->logError('Failed to fetch machine details', $e);

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

    /**
     * Fetch location data for vehicle
     *
     * @return array<mixed>
     */
    public function fetchLocation(string $machineId): array
    {
        try {
            $response = $this->makeRequest('GET', "/fleetmatic/v1/vehicles/{$machineId}/location");

            return [
                'success' => true,
                'location' => $this->parseLocation($response),
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
     * Fetch operational metrics for vehicle
     *
     * @return array<mixed>
     */
    public function fetchMetrics(string $machineId): array
    {
        try {
            // Fetch multiple metric types from Fleetmatic
            $telemetry = $this->makeRequest('GET', "/fleetmatic/v1/vehicles/{$machineId}/telemetry");
            $fuel = $this->makeRequest('GET', "/fleetmatic/v1/vehicles/{$machineId}/fuel");
            $engine = $this->makeRequest('GET', "/fleetmatic/v1/vehicles/{$machineId}/engine");
            $trips = $this->makeRequest('GET', "/fleetmatic/v1/vehicles/{$machineId}/trips");

            $metrics = [];

            // Parse telemetry
            if (! empty($telemetry['data'])) {
                foreach ($telemetry['data'] as $metric) {
                    $metrics[] = $this->parseMetric($metric);
                }
            }

            // Add fuel data
            if (! empty($fuel['fuelLevel'])) {
                $metrics[] = [
                    'type' => 'fuel_level',
                    'value' => $fuel['fuelLevel'],
                    'unit' => 'liters',
                    'timestamp' => $fuel['timestamp'] ?? now(),
                ];
            }

            // Add engine data
            if (! empty($engine['engineData'])) {
                $metrics[] = [
                    'type' => 'engine_status',
                    'value' => $engine['engineData'],
                    'timestamp' => $engine['timestamp'] ?? now(),
                ];
            }

            // Add trip data
            if (! empty($trips['recentTrips'])) {
                $metrics[] = [
                    'type' => 'trips',
                    'value' => count($trips['recentTrips']),
                    'timestamp' => now(),
                ];
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
     * Fetch alerts for vehicle
     *
     * @return array<mixed>
     */
    public function fetchAlerts(string $machineId): array
    {
        try {
            $response = $this->makeRequest('GET', "/fleetmatic/v1/vehicles/{$machineId}/alerts");

            $alerts = [];
            if (! empty($response['alerts'])) {
                foreach ($response['alerts'] as $alert) {
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
     * Parse truck data from Bell format
     *
     * @return array<mixed>
     */
    protected function parseMachineData(array $data): array
    {
        return [
            'external_id' => $data['id'] ?? $data['truck_id'] ?? null,
            'name' => $data['name'] ?? $data['unit_number'] ?? 'Unknown Truck',
            'model' => $data['model'] ?? $data['model_name'] ?? 'Unknown Model',
            'manufacturer' => 'Bell',
            'status' => $this->parseStatus($data['status'] ?? 'unknown'),
            'location' => $this->parseLocation($data['location'] ?? []),
            'last_heartbeat' => $data['last_heartbeat'] ?? $data['last_update'] ?? null,
            'specifications' => [
                'type' => 'haul_truck',
                'unit_number' => $data['unit_number'] ?? null,
                'serial_number' => $data['serial_number'] ?? null,
                'year_manufactured' => $data['year_manufactured'] ?? null,
                'payload_capacity' => $data['payload_capacity'] ?? null,
                'engine_type' => $data['engine_type'] ?? null,
            ],
        ];
    }

    /**
     * Parse location data from Bell format
     *
     * @return array<mixed>
     */
    protected function parseLocation(array $data): array
    {
        return [
            'latitude' => $data['latitude'] ?? $data['lat'] ?? 0,
            'longitude' => $data['longitude'] ?? $data['lng'] ?? 0,
            'altitude' => $data['altitude'] ?? 0,
            'accuracy' => $data['gps_accuracy'] ?? $data['accuracy'] ?? 0,
            'timestamp' => $data['timestamp'] ?? now()->toIso8601String(),
        ];
    }

    /**
     * Parse metric data from Bell format
     *
     * @return array<mixed>
     */
    protected function parseMetric(array $data): array
    {
        return [
            'type' => $data['name'] ?? $data['type'] ?? 'unknown',
            'value' => $data['value'] ?? 0,
            'unit' => $data['unit'] ?? '',
            'timestamp' => $data['timestamp'] ?? now()->toIso8601String(),
            'tags' => [
                'sensor' => $data['sensor'] ?? null,
                'system' => $data['system'] ?? null,
            ],
        ];
    }

    /**
     * Parse alert data from Bell format
     *
     * @return array<mixed>
     */
    protected function parseAlert(array $data): array
    {
        return [
            'external_id' => $data['id'] ?? $data['alert_id'] ?? null,
            'type' => $this->mapAlertType($data['type'] ?? $data['code'] ?? 'unknown'),
            'priority' => $this->mapAlertPriority($data['priority'] ?? $data['severity'] ?? 'medium'),
            'message' => $data['message'] ?? $data['description'] ?? 'Alert from Bell',
            'timestamp' => $data['timestamp'] ?? $data['created_at'] ?? now()->toIso8601String(),
            'acknowledged' => $data['acknowledged'] ?? false,
            'raw_data' => $data,
        ];
    }

    /**
     * Map Bell truck status to standard status
     */
    protected function parseStatus(string $status): string
    {
        $statusMap = [
            'online' => 'active',
            'offline' => 'inactive',
            'idle' => 'idle',
            'haul' => 'active',
            'loading' => 'active',
            'unloading' => 'active',
            'en_route' => 'active',
            'maintenance' => 'maintenance',
            'service' => 'maintenance',
            'disabled' => 'stopped',
            'error' => 'error',
        ];

        return $statusMap[strtolower($status)] ?? 'unknown';
    }
}
