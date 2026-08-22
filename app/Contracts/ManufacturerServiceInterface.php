<?php

namespace App\Contracts;

use Illuminate\Support\Carbon;

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
     *
     * @return array<string, mixed>|null
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
     * Fetch cumulative production counter readings (load counts, payload
     * totals) for a machine over a time window. Providers without a real
     * production data source keep BaseManufacturerService's default, which
     * reports success=false so no production records are ever derived from
     * guessed data.
     *
     * @return array{success: bool, load_count_readings: list<array{timestamp: string, value: float, units: ?string}>, payload_readings: list<array{timestamp: string, value: float, units: ?string}>}
     */
    public function fetchMachineProduction(string $machineId, Carbon $start, Carbon $end): array;

    /**
     * Get API error if any occurred
     */
    public function getLastError(): ?string;
}
