<?php

namespace App\Contracts;

interface ManufacturerServiceInterface
{
    /**
     * Test the connection to the manufacturer API
     */
    public function testConnection(): bool;

    /**
     * Fetch all machines from the manufacturer API
     *
     * @return array<mixed>
     */
    public function fetchMachines(): array;

    /**
     * Fetch machine details from the manufacturer API
     *
     * @return array<mixed>
     */
    public function fetchMachineDetails(string $machineId): array;

    /**
     * Fetch real-time location for a machine
     */
    public function fetchMachineLocation(string $machineId): ?array;

    /**
     * Fetch machine metrics/diagnostics (fuel, temperature, RPM, etc.)
     *
     * @return array<mixed>
     */
    public function fetchMachineMetrics(string $machineId): array;

    /**
     * Fetch machine alerts/faults
     *
     * @return array<mixed>
     */
    public function fetchMachineAlerts(string $machineId): array;

    /**
     * Fetch all data for a machine (comprehensive sync)
     *
     * @return array<mixed>
     */
    public function fetchMachineData(string $machineId): array;

    /**
     * Get the manufacturer name
     */
    public function getManufacturer(): string;

    /**
     * Get API error if any occurred
     */
    public function getLastError(): ?string;
}
