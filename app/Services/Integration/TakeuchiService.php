<?php

namespace App\Services\Integration;

use App\Contracts\ManufacturerServiceInterface;

class TakeuchiService extends BaseManufacturerService implements ManufacturerServiceInterface
{
    protected string $manufacturer = 'takeuchi';

    #[\Override]
    public function testConnection(): bool
    {
        // No real Takeuchi API integration has been built yet -- this
        // used to unconditionally return true, reporting a successful
        // connection test regardless of whether any credentials were
        // even provided.
        $this->lastError = 'Takeuchi integration is not yet available.';

        return false;
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function fetchMachines(): array
    {
        // Implement Takeuchi API fetch logic
        return [];
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function fetchMachineDetails(string $machineId): array
    {
        // Implement Takeuchi API fetch machine details
        return [];
    }

    #[\Override]
    public function fetchMachineLocation(string $machineId): ?array
    {
        // Implement Takeuchi API fetch machine location
        return null;
    }

    #[\Override]
    public function fetchMachineMetrics(string $machineId): array
    {
        // Implement Takeuchi API fetch machine metrics
        return [];
    }

    #[\Override]
    public function fetchMachineAlerts(string $machineId): array
    {
        // Implement Takeuchi API fetch machine alerts
        return [];
    }

    #[\Override]
    public function getLastError(): ?string
    {
        // Return last error if any
        return $this->lastError;
    }
}
