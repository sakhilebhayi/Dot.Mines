<?php

namespace App\Services\AI;

/**
 * Pre-aggregated operating-hours window for one machine (max/min/avg/count
 * over a trailing period). Produced in ONE grouped query for a whole fleet
 * by MaintenancePredictorAgent::metricWindowsForMachines() so the per-machine
 * risk helpers stop issuing their own round trips.
 */
final class MachineMetricWindow
{
    public function __construct(
        public readonly float $maxHours,
        public readonly float $minHours,
        public readonly float $avgHours,
        public readonly int $readingCount,
    ) {}
}
