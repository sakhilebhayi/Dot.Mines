<?php

namespace App\Services\Integration;

use App\Contracts\ManufacturerServiceInterface;

class AtlasCopcoService extends BaseManufacturerService implements ManufacturerServiceInterface
{
    protected string $manufacturer = 'atlas_copco';

    public function testConnection(): bool
    {
        // No real Atlas Copco API integration has been built yet -- this
        // used to unconditionally return true, reporting a successful
        // connection test regardless of whether any credentials were
        // even provided.
        $this->lastError = 'Atlas Copco integration is not yet available.';

        return false;
    }

    public function fetchMachines(): array
    {
        // Implement Atlas Copco API fetch logic
        return [];
    }

    public function fetchMachineDetails(string $machineId): array
    {
        // Implement Atlas Copco API fetch machine details
        return [];
    }

    public function fetchMachineLocation(string $machineId): ?array
    {
        // Implement Atlas Copco API fetch machine location
        return null;
    }

    public function fetchMachineMetrics(string $machineId): array
    {
        // Implement Atlas Copco API fetch machine metrics
        return [];
    }

    public function fetchMachineAlerts(string $machineId): array
    {
        // Implement Atlas Copco API fetch machine alerts
        return [];
    }

    public function fetchMachineData(string $machineId): array
    {
        // Implement Atlas Copco API fetch comprehensive machine data
        return [];
    }

    public function getManufacturer(): string
    {
        return $this->manufacturer;
    }

    public function getLastError(): ?string
    {
        // Return last error if any
        return $this->lastError;
    }
}
