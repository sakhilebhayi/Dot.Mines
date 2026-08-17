<?php

namespace App\Services\Integration;

use App\Contracts\ManufacturerServiceInterface;
use Exception;

/**
 * Doosan DoosanCONNECT Integration Service
 *
 * API Documentation: https://developer.doosan.com/connect-api
 * Requires Doosan account ID
 */
class DoosanService extends BaseManufacturerService implements ManufacturerServiceInterface
{
    protected string $manufacturer = 'doosan';

    public function testConnection(): bool
    {
        try {
            $response = $this->makeRequest('GET', '/api/v2/machines', [
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
            $response = $this->makeRequest('GET', '/api/v2/machines');

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

    public function fetchLocation(string $machineId): array
    {
        try {
            $response = $this->makeRequest('GET', "/api/v2/machines/{$machineId}/location");

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
            $operation = $this->makeRequest('GET', "/api/v2/machines/{$machineId}/operation");
            $fuel = $this->makeRequest('GET', "/api/v2/machines/{$machineId}/fuel");
            $maintenance = $this->makeRequest('GET', "/api/v2/machines/{$machineId}/maintenance");

            // array_merge() would let whichever source is listed last
            // silently overwrite every field from the earlier ones, since
            // parseMetrics() always returns the same set of keys -- see
            // mergeMetricsPreferNonNull().
            $metrics = $this->mergeMetricsPreferNonNull(
                $this->parseMetrics($operation['data'] ?? []),
                $this->parseMetrics($fuel['data'] ?? []),
                $this->parseMetrics($maintenance['data'] ?? [])
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
            $response = $this->makeRequest('GET', "/api/v2/machines/{$machineId}/warnings");

            $alerts = [];
            if (! empty($response['data']['warnings'])) {
                foreach ($response['data']['warnings'] as $warning) {
                    $alerts[] = $this->parseAlert($warning);
                }
            }

            return [
                'success' => true,
                'alerts' => $alerts,
            ];
        } catch (Exception $e) {
            $this->logError('Failed to fetch warnings', $e);

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
     * Fetch machine details from Doosan API
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
