<?php

namespace App\Services\AI;

use App\Models\Machine;
use App\Models\Team;

/**
 * Anomaly Detector AI Agent
 */
class AnomalyDetectorAgent
{
    /**
     * @return array<mixed>
     */
    public function analyze(Team $team): array
    {
        $recommendations = [];
        $insights = [];

        $machines = Machine::where('team_id', $team->id)->get();

        foreach ($machines as $machine) {
            // Check for location anomalies
            if ($machine->latitude && $machine->longitude) {
                // Check if machine is outside designated areas
                if ($machine->mine_area_id === null && $machine->status === 'active') {
                    $insights[] = [
                        'type' => 'anomaly',
                        'category' => 'fleet',
                        'severity' => 'warning',
                        'title' => 'Machine Outside Designated Area',
                        'description' => "{$machine->name} is active but not assigned to any mine area",
                        'data' => ['machine_id' => $machine->id],
                    ];
                }
            }

            // Check for status anomalies
            $lastUpdate = $machine->updated_at;
            if ($lastUpdate && now()->diffInHours($lastUpdate) > 24) {
                $insights[] = [
                    'type' => 'anomaly',
                    'category' => 'fleet',
                    'severity' => 'warning',
                    'title' => 'Stale Machine Data',
                    'description' => "{$machine->name} hasn't reported data in 24+ hours",
                    'data' => [
                        'machine_id' => $machine->id,
                        'hours_since_update' => now()->diffInHours($lastUpdate),
                    ],
                ];
            }
        }

        return [
            'recommendations' => $recommendations,
            'insights' => $insights,
        ];
    }
}
