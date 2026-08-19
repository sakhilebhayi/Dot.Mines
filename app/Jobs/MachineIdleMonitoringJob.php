<?php

namespace App\Jobs;

use App\Models\Alert;
use App\Models\Machine;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MachineIdleMonitoringJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    private const IDLE_THRESHOLD_MINUTES = 20;

    private const SPEED_THRESHOLD = 2; // km/h - considered stationary if below this

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->onQueue('monitoring');
    }

    /**
     * Execute the job - monitors machines in production for excessive idle time.
     */
    public function handle(): void
    {
        Log::info('Starting machine idle monitoring job');

        try {
            // Get all machines currently in production (active status)
            $machines = Machine::where('status', 'active')
                ->with('team')
                ->get();

            if ($machines->isEmpty()) {
                Log::debug('No active machines found for idle monitoring');

                return;
            }

            $idleMachinesDetected = 0;

            foreach ($machines as $machine) {
                // Check if machine has been idle for more than 20 minutes
                $idleStatus = $this->checkMachineIdleStatus($machine);

                if ($idleStatus['is_idle'] && $idleStatus['idle_duration'] >= self::IDLE_THRESHOLD_MINUTES) {
                    $this->createIdleAlert($machine, $idleStatus);
                    $idleMachinesDetected++;
                }
            }

            Log::info('Machine idle monitoring completed', [
                'machines_checked' => $machines->count(),
                'idle_machines_detected' => $idleMachinesDetected,
            ]);

        } catch (\Exception $e) {
            Log::error('Machine idle monitoring job failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Check if machine has been idle/stationary
     */
    private function checkMachineIdleStatus(Machine $machine): array
    {
        // Get metrics from last 30 minutes
        $recentMetrics = DB::table('machine_metrics')
            ->where('machine_id', $machine->id)
            ->where('created_at', '>=', now()->subMinutes(30))
            ->orderBy('created_at', 'desc')
            ->get();

        if ($recentMetrics->isEmpty()) {
            return ['is_idle' => false, 'idle_duration' => 0];
        }

        // Check if machine has been stationary
        $stationaryPeriods = [];
        $currentStationaryStart = null;
        $lastLocation = null;
        $lastTime = null;

        foreach ($recentMetrics->reverse() as $metric) {
            $isStationary = false;

            // Check speed (if available)
            if (! is_null($metric->speed) && $metric->speed < self::SPEED_THRESHOLD) {
                $isStationary = true;
            }

            // Check location change (if available)
            if (! is_null($metric->latitude) && ! is_null($metric->longitude)) {
                if ($lastLocation) {
                    $distance = $this->calculateDistance(
                        $lastLocation['lat'],
                        $lastLocation['lng'],
                        $metric->latitude,
                        $metric->longitude
                    );

                    // If moved less than 50 meters, consider stationary
                    if ($distance < 0.05) { // 0.05 km = 50 meters
                        $isStationary = true;
                    }
                }

                $lastLocation = ['lat' => $metric->latitude, 'lng' => $metric->longitude];
            }

            if ($isStationary) {
                if (! $currentStationaryStart) {
                    $currentStationaryStart = $metric->created_at;
                }
            } else {
                if ($currentStationaryStart) {
                    $stationaryPeriods[] = [
                        'start' => $currentStationaryStart,
                        'end' => $lastTime ?? $metric->created_at,
                    ];
                    $currentStationaryStart = null;
                }
            }

            $lastTime = $metric->created_at;
        }

        // If still stationary at end
        if ($currentStationaryStart) {
            $stationaryPeriods[] = [
                'start' => $currentStationaryStart,
                'end' => now(),
            ];
        }

        // Find longest stationary period
        $longestIdleDuration = 0;
        $longestIdleStart = null;

        foreach ($stationaryPeriods as $period) {
            $start = is_string($period['start']) ? Carbon::parse($period['start']) : $period['start'];
            $end = is_string($period['end']) ? Carbon::parse($period['end']) : $period['end'];

            $duration = $start->diffInMinutes($end);

            if ($duration > $longestIdleDuration) {
                $longestIdleDuration = $duration;
                $longestIdleStart = $start;
            }
        }

        return [
            'is_idle' => $longestIdleDuration >= self::IDLE_THRESHOLD_MINUTES,
            'idle_duration' => $longestIdleDuration,
            'idle_since' => $longestIdleStart,
            'last_location' => $lastLocation,
        ];
    }

    /**
     * Calculate distance between two coordinates in kilometers
     */
    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Create idle machine alert
     */
    private function createIdleAlert(Machine $machine, array $idleStatus): void
    {
        // Check if there's already a recent active alert
        $existingAlert = Alert::where('machine_id', $machine->id)
            ->where('type', 'machine_idle')
            ->where('status', 'active')
            ->where('created_at', '>=', now()->subHour())
            ->first();

        if ($existingAlert) {
            // Don't create duplicate alerts
            return;
        }

        $idleDuration = $idleStatus['idle_duration'];
        $priority = $idleDuration >= 40 ? 'high' : 'medium';

        Alert::create([
            'team_id' => $machine->team_id,
            'machine_id' => $machine->id,
            'type' => 'machine_idle',
            'title' => 'Machine Idle in Production',
            'description' => sprintf(
                'Machine %s has been stationary/idle for %d minutes while marked as active in production. This may indicate an issue or inefficiency.',
                $machine->name,
                $idleDuration
            ),
            'priority' => $priority,
            'status' => 'active',
            'triggered_at' => now(),
            'metadata' => [
                'idle_duration_minutes' => $idleDuration,
                'idle_since' => $idleStatus['idle_since']?->toDateTimeString(),
                'latitude' => $idleStatus['last_location']['lat'] ?? null,
                'longitude' => $idleStatus['last_location']['lng'] ?? null,
                'machine_status' => $machine->status,
            ],
        ]);

        Log::info('Machine idle alert created', [
            'machine_id' => $machine->id,
            'machine_name' => $machine->name,
            'idle_duration' => $idleDuration,
        ]);
    }
}
