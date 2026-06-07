<?php

namespace App\Jobs;

use App\Models\Alert;
use App\Models\Machine;
use App\Models\Route;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RouteSpeedMonitoringJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->onQueue('monitoring');
    }

    /**
     * Execute the job - monitors machine speeds on routes and triggers alerts
     * when speed limits are exceeded.
     */
    public function handle(): void
    {
        Log::info('Starting route speed monitoring job');

        try {
            // Get all active routes with speed limits
            $routes = Route::where('status', 'active')
                ->whereNotNull('speed_limit')
                ->whereNotNull('machine_id')
                ->with(['machine'])
                ->get();

            if ($routes->isEmpty()) {
                Log::debug('No active routes with speed limits found');

                return;
            }

            $violationsDetected = 0;

            foreach ($routes as $route) {
                if (! $route->machine) {
                    continue;
                }

                // Check if machine is on this route and exceeding speed limit
                // Get the most recent metrics for this machine (last 5 minutes)
                $recentMetrics = DB::table('machine_metrics')
                    ->where('machine_id', $route->machine_id)
                    ->where('created_at', '>=', now()->subMinutes(5))
                    ->whereNotNull('speed')
                    ->whereNotNull('latitude')
                    ->whereNotNull('longitude')
                    ->orderBy('created_at', 'desc')
                    ->get();

                if ($recentMetrics->isEmpty()) {
                    continue;
                }

                foreach ($recentMetrics as $metric) {
                    // Check if machine is near the route (within reasonable distance)
                    $isOnRoute = $this->isMachineOnRoute(
                        $metric->latitude,
                        $metric->longitude,
                        $route
                    );

                    if ($isOnRoute && $metric->speed > $route->speed_limit) {
                        // Speed violation detected
                        $this->createSpeedViolationAlert($route, $metric);
                        $violationsDetected++;
                        break; // Only one alert per route per run
                    }
                }
            }

            Log::info('Route speed monitoring completed', [
                'routes_checked' => $routes->count(),
                'violations_detected' => $violationsDetected,
            ]);

        } catch (\Exception $e) {
            Log::error('Route speed monitoring job failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Check if machine coordinates are on the route
     */
    private function isMachineOnRoute(float $lat, float $lon, Route $route): bool
    {
        // Check if machine is near start or end points
        $distanceFromStart = $this->calculateDistance(
            $lat,
            $lon,
            $route->start_latitude,
            $route->start_longitude
        );

        $distanceFromEnd = $this->calculateDistance(
            $lat,
            $lon,
            $route->end_latitude,
            $route->end_longitude
        );

        // If within 1km of start or end, consider on route
        if ($distanceFromStart <= 1.0 || $distanceFromEnd <= 1.0) {
            return true;
        }

        // Check waypoints if available
        $waypoints = DB::table('waypoints')
            ->where('route_id', $route->id)
            ->get();

        foreach ($waypoints as $waypoint) {
            $distanceFromWaypoint = $this->calculateDistance(
                $lat,
                $lon,
                $waypoint->latitude,
                $waypoint->longitude
            );

            if ($distanceFromWaypoint <= 0.5) { // Within 500m of waypoint
                return true;
            }
        }

        return false;
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
     * Create speed violation alert
     */
    private function createSpeedViolationAlert(Route $route, mixed $metric): void
    {
        // Check if there's already a recent active alert for this violation
        $existingAlert = Alert::where('machine_id', $route->machine_id)
            ->where('type', 'speed_violation')
            ->where('status', 'active')
            ->where('created_at', '>=', now()->subMinutes(15))
            ->whereJsonContains('metadata->route_id', $route->id)
            ->first();

        if ($existingAlert) {
            // Don't create duplicate alerts
            return;
        }

        Alert::create([
            'team_id' => $route->team_id,
            'machine_id' => $route->machine_id,
            'type' => 'speed_violation',
            'title' => 'Speed Limit Exceeded',
            'description' => sprintf(
                'Machine %s exceeded the speed limit of %d km/h on route "%s". Current speed: %d km/h.',
                $route->machine?->name ?? 'Unknown',
                $route->speed_limit,
                $route->name,
                (int) $metric->speed
            ),
            'priority' => $metric->speed > ($route->speed_limit * 1.5) ? 'high' : 'medium',
            'status' => 'active',
            'triggered_at' => now(),
            'metadata' => [
                'route_id' => $route->id,
                'route_name' => $route->name,
                'speed_limit' => $route->speed_limit,
                'current_speed' => (int) $metric->speed,
                'latitude' => $metric->latitude,
                'longitude' => $metric->longitude,
                'timestamp' => $metric->created_at,
            ],
        ]);

        Log::info('Speed violation alert created', [
            'machine_id' => $route->machine_id,
            'route_id' => $route->id,
            'speed_limit' => $route->speed_limit,
            'current_speed' => $metric->speed,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('RouteSpeedMonitoringJob permanently failed', [
            'error' => $exception->getMessage(),
        ]);
    }
}
