<?php

namespace App\Services\Integration;

use App\Contracts\ManufacturerServiceInterface;
use Exception;

/**
 * Epiroc Certiq Integration Service (formerly Atlas Copco)
 *
 * API Documentation: https://certiq.com/api-documentation
 * Requires Epiroc customer ID and Certiq subscription
 */
class EpirocService extends BaseManufacturerService implements ManufacturerServiceInterface
{
    protected string $manufacturer = 'epiroc';

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
            $response = $this->makeRequest('GET', "/api/v2/equipment/{$machineId}/location");

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
     * @return array<mixed>
     */
    public function fetchMetrics(string $machineId): array
    {
        try {
            $performance = $this->makeRequest('GET', "/api/v2/equipment/{$machineId}/performance");
            $production = $this->makeRequest('GET', "/api/v2/equipment/{$machineId}/production");
            $maintenance = $this->makeRequest('GET', "/api/v2/equipment/{$machineId}/maintenance");

            $metrics = array_merge(
                $this->parseMetrics($performance['data'] ?? []),
                $this->parseMetrics($production['data'] ?? []),
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

    /**
     * @return array<mixed>
     */
    public function fetchAlerts(string $machineId): array
    {
        try {
            $response = $this->makeRequest('GET', "/api/v2/equipment/{$machineId}/events");

            $alerts = [];
            if (! empty($response['data']['events'])) {
                foreach ($response['data']['events'] as $event) {
                    $alerts[] = $this->parseAlert($event);
                }
            }

            return [
                'success' => true,
                'alerts' => $alerts,
            ];
        } catch (Exception $e) {
            $this->logError('Failed to fetch events', $e);

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
     * Fetch machine details from Epiroc API
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
