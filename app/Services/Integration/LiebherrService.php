<?php

namespace App\Services\Integration;

use App\Contracts\ManufacturerServiceInterface;
use Exception;

/**
 * Liebherr LiDAT Integration Service
 *
 * API Documentation: https://www.liebherr.com/lidat-api
 * Requires Liebherr customer ID
 */
class LiebherrService extends BaseManufacturerService implements ManufacturerServiceInterface
{
    protected string $manufacturer = 'liebherr';

    public function testConnection(): bool
    {
        try {
            $response = $this->makeRequest('GET', '/api/v2/equipment', [
                'query' => ['limit' => 1],
            ]);

            return ! empty($response) && $response['success'] !== false;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }

    /**
     * @return array<mixed>
     */
    public function fetchMachines(): array
    {
        try {
            $response = $this->makeRequest('GET', '/api/v2/equipment');

            $machines = [];
            if (! empty($response['data']['equipment'])) {
                foreach ($response['data']['equipment'] as $equipment) {
                    $machines[] = $this->parseMachineData($equipment);
                }
            }

            return [
                'success' => true,
                'machines' => $machines,
                'count' => count($machines),
            ];
        } catch (Exception $e) {
            $this->logError('Failed to fetch equipment', $e);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'machines' => [],
            ];
        }
    }

    /**
     * @return array<mixed>
     */
    public function fetchLocation(string $machineId): array
    {
        try {
            $response = $this->makeRequest('GET', "/api/v2/equipment/{$machineId}/position");

            return [
                'success' => true,
                'location' => $this->parseLocation($response['data'] ?? []),
            ];
        } catch (Exception $e) {
            $this->logError('Failed to fetch position', $e);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<mixed>
     */
    public function fetchMetrics(string $machineId): array
    {
        try {
            $operatingData = $this->makeRequest('GET', "/api/v2/equipment/{$machineId}/operating-data");
            $telemetry = $this->makeRequest('GET', "/api/v2/equipment/{$machineId}/telemetry");
            $serviceIntervals = $this->makeRequest('GET', "/api/v2/equipment/{$machineId}/service-intervals");

            $metrics = array_merge(
                $this->parseMetrics($operatingData['data'] ?? []),
                $this->parseMetrics($telemetry['data'] ?? []),
                $this->parseMetrics($serviceIntervals['data'] ?? [])
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

    /**
     * @return array<mixed>
     */
    public function fetchAlerts(string $machineId): array
    {
        try {
            $response = $this->makeRequest('GET', "/api/v2/equipment/{$machineId}/error-codes");

            $alerts = [];
            if (! empty($response['data']['errorCodes'])) {
                foreach ($response['data']['errorCodes'] as $error) {
                    $alerts[] = $this->parseAlert($error);
                }
            }

            return [
                'success' => true,
                'alerts' => $alerts,
            ];
        } catch (Exception $e) {
            $this->logError('Failed to fetch error codes', $e);

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
     * Fetch machine details from Liebherr API
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
}
