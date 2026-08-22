<?php

namespace App\Services\Integration;

use App\Contracts\ManufacturerServiceInterface;

class AtlasCopcoService extends BaseManufacturerService implements ManufacturerServiceInterface
{
    protected string $manufacturer = 'atlas_copco';

    #[\Override]
    public function testConnection(): bool
    {
        // No real Atlas Copco API integration has been built yet -- this
        // used to unconditionally return true, reporting a successful
        // connection test regardless of whether any credentials were
        // even provided.
        $this->lastError = 'Atlas Copco integration is not yet available.';

        return false;
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function fetchMachines(): array
    {
        // Implement Atlas Copco API fetch logic
        return [];
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function fetchMachineDetails(string $machineId): array
    {
        // Implement Atlas Copco API fetch machine details
        return [];
    }

    #[\Override]
    public function fetchMachineLocation(string $machineId): ?array
    {
        // Implement Atlas Copco API fetch machine location
        return null;
    }

    #[\Override]
    public function fetchMachineMetrics(string $machineId): array
    {
        // Implement Atlas Copco API fetch machine metrics
        return [];
    }

    #[\Override]
    public function fetchMachineAlerts(string $machineId): array
    {
        // Implement Atlas Copco API fetch machine alerts
        return [];
    }

    #[\Override]
    public function getLastError(): ?string
    {
        // Return last error if any
        return $this->lastError;
    }
}
