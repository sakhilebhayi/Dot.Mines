<?php

namespace App\Jobs;

use App\Events\AlertTriggered;
use App\Models\Alert;
use App\Models\Machine;
use App\Models\Team;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AlertGenerationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Team $team;

    public int $tries = 2;

    public int $timeout = 60;

    /** @var list<int> */
    public array $backoff = [30, 120]; // 30s, 2 mins

    /**
     * Create a new job instance.
     */
    public function __construct(Team $team)
    {
        $this->team = $team;
        $this->onQueue('alerts');
    }

    /**
     * Execute the job - analyzes machine metrics and generates alerts.
     * Broadcasts alerts in real-time to all team members.
     */
    public function handle(): void
    {
        Log::info('Starting alert generation job', [
            'team_id' => $this->team->id,
        ]);

        try {
            $generatedAlerts = [];

            // Check each machine for alert conditions
            $machines = $this->team->machines()->where('status', '!=', 'offline')->get();

            foreach ($machines as $machine) {
                $alerts = $this->generateAlertsForMachine($machine);

                // Deactivate previous active alerts if new alerts aren't present
                foreach ($alerts as $alertData) {
                    $generatedAlerts[] = $this->createOrUpdateAlert($machine, $alertData);
                }
            }

            Log::info('Alert generation job completed', [
                'team_id' => $this->team->id,
                'alerts_generated' => count($generatedAlerts),
            ]);

        } catch (\Exception $e) {
            Log::error('Alert generation job failed', [
                'team_id' => $this->team->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate alerts for a specific machine based on its current state.
     *
     * @return list<array<string, mixed>>
     */
    private function generateAlertsForMachine(Machine $machine): array
    {
        $alerts = [];

        // Get latest metrics for this machine
        $metrics = $machine->metrics()
            ->latest()
            ->first();

        if (! $metrics) {
            return $alerts;
        }

        // Check fuel level
        if ($machine->fuel_capacity && ($metrics->fuel_level !== null && $metrics->fuel_level !== 0.0)) {
            $fuelPercentage = ($metrics->fuel_level / $machine->fuel_capacity) * 100;

            if ($fuelPercentage <= 10) {
                $alerts[] = [
                    'type' => 'fuel',
                    'title' => 'Critical Fuel Level',
                    'description' => sprintf(
                        '%s has only %.0f%% fuel remaining. Refuel immediately.',
                        $machine->name,
                        $fuelPercentage
                    ),
                    'priority' => 'critical',
                    'metadata' => [
                        'fuel_level' => $metrics->fuel_level,
                        'fuel_capacity' => $machine->fuel_capacity,
                        'percentage' => $fuelPercentage,
                    ],
                ];
            } elseif ($fuelPercentage <= 25) {
                $alerts[] = [
                    'type' => 'fuel',
                    'title' => 'Low Fuel Level',
                    'description' => sprintf(
                        '%s is running low on fuel (%.0f%%). Plan refueling soon.',
                        $machine->name,
                        $fuelPercentage
                    ),
                    'priority' => 'high',
                    'metadata' => [
                        'fuel_level' => $metrics->fuel_level,
                        'fuel_capacity' => $machine->fuel_capacity,
                        'percentage' => $fuelPercentage,
                    ],
                ];
            }
        }

        // Check engine temperature
        if (($metrics->engine_temperature !== null && $metrics->engine_temperature !== 0.0)) {
            if ($metrics->engine_temperature > 95) {
                $alerts[] = [
                    'type' => 'temperature',
                    'title' => 'High Engine Temperature',
                    'description' => sprintf(
                        '%s engine temperature is critically high at %d°C. Stop operations and investigate.',
                        $machine->name,
                        $metrics->engine_temperature
                    ),
                    'priority' => 'critical',
                    'metadata' => [
                        'temperature' => $metrics->engine_temperature,
                        'threshold' => 95,
                    ],
                ];
            } elseif ($metrics->engine_temperature > 85) {
                $alerts[] = [
                    'type' => 'temperature',
                    'title' => 'Elevated Engine Temperature',
                    'description' => sprintf(
                        '%s engine temperature is elevated at %d°C. Monitor closely.',
                        $machine->name,
                        $metrics->engine_temperature
                    ),
                    'priority' => 'medium',
                    'metadata' => [
                        'temperature' => $metrics->engine_temperature,
                        'threshold' => 85,
                    ],
                ];
            }
        }

        // Check hours meter for maintenance
        if ($machine->hours_meter) {
            $hoursUntilMaintenance = $this->getHoursUntilMaintenance($machine);

            if ($hoursUntilMaintenance !== null) {
                if ($hoursUntilMaintenance <= 0) {
                    $alerts[] = [
                        'type' => 'maintenance',
                        'title' => 'Maintenance Overdue',
                        'description' => sprintf(
                            '%s is overdue for maintenance. Current hours: %.0f',
                            $machine->name,
                            $machine->hours_meter
                        ),
                        'priority' => 'high',
                        'metadata' => [
                            'hours_meter' => $machine->hours_meter,
                        ],
                    ];
                } elseif ($hoursUntilMaintenance <= 20) {
                    $alerts[] = [
                        'type' => 'maintenance',
                        'title' => 'Maintenance Due Soon',
                        'description' => sprintf(
                            '%s will require maintenance in %.0f hours',
                            $machine->name,
                            $hoursUntilMaintenance
                        ),
                        'priority' => 'medium',
                        'metadata' => [
                            'hours_meter' => $machine->hours_meter,
                            'hours_until_maintenance' => $hoursUntilMaintenance,
                        ],
                    ];
                }
            }
        }

        // Check offline duration
        if ($machine->status === 'offline') {
            $offlineDuration = $machine->last_location_update?->diffInHours(now()) ?? null;

            if ($offlineDuration !== null && $offlineDuration > 1) {
                $alerts[] = [
                    'type' => 'connectivity',
                    'title' => 'Machine Offline',
                    'description' => sprintf(
                        '%s has been offline for %d hours. Check communication links.',
                        $machine->name,
                        $offlineDuration
                    ),
                    'priority' => $offlineDuration > 4 ? 'high' : 'medium',
                    'metadata' => [
                        'offline_hours' => $offlineDuration,
                    ],
                ];
            }
        }

        return $alerts;
    }

    /**
     * Create or update an alert in the database and broadcast it.
     *
     * @param  array<string, mixed>  $alertData
     */
    private function createOrUpdateAlert(Machine $machine, array $alertData): Alert
    {
        // Check for existing active alert of same type
        $existingAlert = Alert::where('machine_id', $machine->id)
            ->where('type', $alertData['type'])
            ->where('status', 'active')
            ->first();

        if ($existingAlert) {
            // Update existing alert
            $existingAlert->update([
                'title' => $alertData['title'],
                'description' => $alertData['description'],
                'priority' => $alertData['priority'],
                'metadata' => $alertData['metadata'],
                'triggered_at' => now(),
            ]);

            Log::debug('Updated existing alert', [
                'alert_id' => $existingAlert->id,
                'machine_id' => $machine->id,
            ]);

            $alert = $existingAlert;
        } else {
            // Create new alert
            $alert = Alert::create([
                'team_id' => $machine->team_id,
                'machine_id' => $machine->id,
                'type' => $alertData['type'],
                'title' => $alertData['title'],
                'description' => $alertData['description'],
                'priority' => $alertData['priority'],
                'status' => 'active',
                'triggered_at' => now(),
                'metadata' => $alertData['metadata'],
            ]);

            Log::debug('Created new alert', [
                'alert_id' => $alert->id,
                'machine_id' => $machine->id,
                'type' => $alertData['type'],
            ]);
        }

        // Broadcast the alert in real-time
        event(new AlertTriggered($alert));

        return $alert;
    }

    /**
     * Calculate hours until next maintenance based on machine type and hours.
     */
    private function getHoursUntilMaintenance(Machine $machine): ?int
    {
        // Maintenance schedules based on machine type
        $maintenanceSchedule = [
            'volvo' => 500,      // Every 500 operating hours
            'cat' => 500,        // Every 500 operating hours
            'komatsu' => 500,    // Every 500 operating hours
            'bell' => 250,       // Every 250 operating hours
            'ldv' => 200,        // Every 200 operating hours
        ];

        $interval = $maintenanceSchedule[strtolower($machine->machine_type)] ?? null;

        if (! $interval) {
            return null;
        }

        // Calculate based on some baseline (you'd store last maintenance time)
        // For now, use modulo to calculate next interval
        $hoursIntoInterval = $machine->hours_meter % $interval;

        return $interval - $hoursIntoInterval;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Alert generation job permanently failed', [
            'team_id' => $this->team->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
