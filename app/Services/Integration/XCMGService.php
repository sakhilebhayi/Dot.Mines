<?php

namespace App\Services\Integration;

use App\Contracts\ManufacturerServiceInterface;
use Exception;

/**
 * XCMG Xrea (XCMG Remote Expert Assistant) Integration Service
 *
 * Contact XCMG for API access
 * Requires XCMG company ID
 */
class XCMGService extends BaseManufacturerService implements ManufacturerServiceInterface
{
    protected string $manufacturer = 'xcmg';

    public function testConnection(): bool
    {
        try {
            $response = $this->makeRequest('GET', '/iot/v1/devices', [
                'query' => ['limit' => 1],
            ]);

            return ! empty($response) && $response['success'] !== false;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }

    public function fetchMachines(): array
    {
        try {
            $response = $this->makeRequest('GET', '/iot/v1/devices');

            $machines = [];
            if (! empty($response['data']['devices'])) {
                foreach ($response['data']['devices'] as $device) {
                    $machines[] = $this->parseMachineData($device);
                }
            }

            return [
                'success' => true,
                'machines' => $machines,
                'count' => count($machines),
            ];
        } catch (Exception $e) {
            $this->logError('Failed to fetch devices', $e);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'machines' => [],
            ];
        }
    }

    public function fetchLocation(string $machineId): array
    {
        try {
            $response = $this->makeRequest('GET', "/iot/v1/devices/{$machineId}/location");

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

    public function fetchMetrics(string $machineId): array
    {
        try {
            $status = $this->makeRequest('GET', "/iot/v1/devices/{$machineId}/status");
            $parameters = $this->makeRequest('GET', "/iot/v1/devices/{$machineId}/parameters");
            $workData = $this->makeRequest('GET', "/iot/v1/devices/{$machineId}/work-data");

            // array_merge() would let whichever source is listed last
            // silently overwrite every field from the earlier ones, since
            // parseMetrics() always returns the same set of keys -- see
            // mergeMetricsPreferNonNull().
            $metrics = $this->mergeMetricsPreferNonNull(
                $this->parseMetrics($status['data'] ?? []),
                $this->parseMetrics($parameters['data'] ?? []),
                $this->parseMetrics($workData['data'] ?? [])
            );

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

    public function fetchAlerts(string $machineId): array
    {
        try {
            $response = $this->makeRequest('GET', "/iot/v1/devices/{$machineId}/faults");

            $alerts = [];
            if (! empty($response['data']['faults'])) {
                foreach ($response['data']['faults'] as $fault) {
                    $alerts[] = $this->parseAlert($fault);
                }
            }

            return [
                'success' => true,
                'alerts' => $alerts,
            ];
        } catch (Exception $e) {
            $this->logError('Failed to fetch faults', $e);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'alerts' => [],
            ];
        }
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Fetch machine details from XCMG API
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
}
