<?php

namespace App\Services;

use App\Models\Geofence;
use App\Models\Route;
use App\Models\Waypoint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Route Planning Service
 *
 * Implements pathfinding algorithms to calculate optimal routes
 * Uses OSRM (Open Source Routing Machine) for road-based routing
 * Considers geofences, fuel stations, and terrain
 */
class RoutePlanningService
{
    protected float $avgSpeed = 40; // km/h

    protected float $fuelConsumption = 0.4; // L/km

    protected string $osrmBaseUrl = 'https://router.project-osrm.org'; // Free public OSRM instance

    /**
     * Calculate optimal route between two points following actual roads
     *
     * @return array<string, mixed>
     */
    public function calculateOptimalRoute(
        float $startLat,
        float $startLon,
        float $endLat,
        float $endLon,
        ?int $machineId = null,
        ?int $teamId = null
    ): array {
        // Get geofences for the team
        $geofences = $teamId ? Geofence::where('team_id', $teamId)->get() : collect();

        // Get road-based route from OSRM
        $roadRoute = $this->getOSRMRoute($startLon, $startLat, $endLon, $endLat);

        if (! $roadRoute) {
            // Fallback to straight-line calculation if OSRM fails
            return $this->calculateStraightLineRoute($startLat, $startLon, $endLat, $endLon);
        }

        // Extract route geometry (array of [lat, lon] coordinates)
        $routeCoordinates = $roadRoute['geometry'];
        $totalDistance = $roadRoute['distance'] / 1000; // Convert meters to km
        $totalDuration = $roadRoute['duration'] / 60; // Convert seconds to minutes

        // Generate waypoints from the route geometry
        // Sample points along the route to avoid too many waypoints
        $waypointData = $this->sampleRouteWaypoints($routeCoordinates, $geofences);

        $estimatedTime = (int) $totalDuration;
        $estimatedFuel = round($totalDistance * $this->fuelConsumption, 2);

        return [
            'start_latitude' => $startLat,
            'start_longitude' => $startLon,
            'end_latitude' => $endLat,
            'end_longitude' => $endLon,
            'total_distance' => round($totalDistance, 2),
            'estimated_time' => $estimatedTime,
            'estimated_fuel' => $estimatedFuel,
            'waypoints' => $waypointData,
            'route_geometry' => $routeCoordinates, // Full route coordinates for map display
        ];
    }

    /**
     * Get route from OSRM routing engine
     *
     * @return array<string, mixed>|null
     */
    protected function getOSRMRoute(float $startLon, float $startLat, float $endLon, float $endLat): ?array
    {
        try {
            // OSRM expects coordinates in lon,lat format
            $url = "{$this->osrmBaseUrl}/route/v1/driving/{$startLon},{$startLat};{$endLon},{$endLat}";

            $response = Http::timeout(10)->get($url, [
                'overview' => 'full',
                'geometries' => 'geojson',
                'steps' => 'true',
            ]);

            if (! $response->successful()) {
                Log::warning('OSRM route request failed', [
                    'status' => $response->status(),
                    'url' => $url,
                ]);

                return null;
            }

            $data = $response->json();

            if ($data['code'] !== 'Ok' || empty($data['routes'])) {
                Log::warning('OSRM returned no valid routes', ['data' => $data]);

                return null;
            }

            $route = $data['routes'][0];

            // Extract geometry coordinates from GeoJSON
            $coordinates = $route['geometry']['coordinates'] ?? [];

            // Convert from [lon, lat] to [lat, lon] format
            $routeCoordinates = array_map(function ($coord) {
                return [$coord[1], $coord[0]]; // Swap to [lat, lon]
            }, $coordinates);

            return [
                'geometry' => $routeCoordinates,
                'distance' => $route['distance'] ?? 0,
                'duration' => $route['duration'] ?? 0,
                'steps' => $route['legs'][0]['steps'] ?? [],
            ];

        } catch (\Exception $e) {
            Log::error('OSRM routing error', [
                'message' => $e->getMessage(),
                'start' => "{$startLat},{$startLon}",
                'end' => "{$endLat},{$endLon}",
            ]);

            return null;
        }
    }

    /**
     * Sample waypoints from route geometry to avoid too many points
     *
     * @param  array<int, mixed>  $routeCoordinates
     * @param  Collection<array-key, mixed>  $geofences
     * @return list<array<string, mixed>>
     */
    protected function sampleRouteWaypoints(array $routeCoordinates, Collection $geofences): array
    {
        if (empty($routeCoordinates)) {
            return [];
        }

        $waypointData = [];
        $totalPoints = count($routeCoordinates);

        // Sample approximately every 10-15 points, or max 20 waypoints
        $sampleInterval = max(1, (int) floor($totalPoints / 20));

        $prevLat = $routeCoordinates[0][0];
        $prevLon = $routeCoordinates[0][1];

        for ($i = $sampleInterval; $i < $totalPoints; $i += $sampleInterval) {
            $lat = $routeCoordinates[$i][0];
            $lon = $routeCoordinates[$i][1];

            $distance = $this->calculateDistance($prevLat, $prevLon, $lat, $lon);

            $waypointData[] = [
                'sequence_order' => count($waypointData) + 1,
                'latitude' => $lat,
                'longitude' => $lon,
                'waypoint_type' => 'navigation',
                'name' => 'Waypoint '.(count($waypointData) + 1),
                'distance_from_previous' => round($distance, 2),
                'estimated_time_from_previous' => (int) (($distance / $this->avgSpeed) * 60),
            ];

            $prevLat = $lat;
            $prevLon = $lon;
        }

        return $waypointData;
    }

    /**
     * Fallback: Calculate straight-line route when OSRM is unavailable
     *
     * @return array<string, mixed>
     */
    protected function calculateStraightLineRoute(
        float $startLat,
        float $startLon,
        float $endLat,
        float $endLon
    ): array {
        $totalDistance = $this->calculateDistance($startLat, $startLon, $endLat, $endLon);
        $estimatedTime = (int) (($totalDistance / $this->avgSpeed) * 60);
        $estimatedFuel = round($totalDistance * $this->fuelConsumption, 2);

        return [
            'start_latitude' => $startLat,
            'start_longitude' => $startLon,
            'end_latitude' => $endLat,
            'end_longitude' => $endLon,
            'total_distance' => round($totalDistance, 2),
            'estimated_time' => $estimatedTime,
            'estimated_fuel' => $estimatedFuel,
            'waypoints' => [],
            'route_geometry' => [[$startLat, $startLon], [$endLat, $endLon]], // Simple straight line
        ];
    }

    /**
     * Generate waypoints avoiding restricted geofences
     *
     * @param  Collection<array-key, mixed>  $geofences
     * @return list<array<string, mixed>>
     */
    protected function generateWaypoints(
        float $startLat,
        float $startLon,
        float $endLat,
        float $endLon,
        Collection $geofences
    ): array {
        $waypoints = [];

        // Get restricted geofences
        $restrictedGeofences = $geofences->filter(function ($geofence) {
            return $geofence->geofence_type === 'restricted';
        });

        // Simple implementation: check if direct path intersects with restricted zones
        // If yes, add waypoints to go around them
        if ($restrictedGeofences->isEmpty()) {
            // Direct route is fine
            return $waypoints;
        }

        // For each restricted geofence, check if path intersects
        foreach ($restrictedGeofences as $geofence) {
            if ($this->pathIntersectsGeofence($startLat, $startLon, $endLat, $endLon, $geofence)) {
                // Add waypoint to avoid this geofence
                $avoidancePoint = $this->calculateAvoidancePoint($startLat, $startLon, $endLat, $endLon, $geofence);

                if ($avoidancePoint) {
                    $waypoints[] = [
                        'latitude' => $avoidancePoint['lat'],
                        'longitude' => $avoidancePoint['lon'],
                        'type' => 'geofence',
                        'name' => "Avoid: {$geofence->name}",
                    ];
                }
            }
        }

        return $waypoints;
    }

    /**
     * Check if path intersects with geofence
     */
    protected function pathIntersectsGeofence(
        float $startLat,
        float $startLon,
        float $endLat,
        float $endLon,
        Geofence $geofence
    ): bool {
        // Simple bounding box check
        $coordinates = is_string($geofence->coordinates) ? json_decode($geofence->coordinates, true) : $geofence->coordinates;

        if (empty($coordinates)) {
            return false;
        }

        // Get geofence bounds
        $lats = array_column($coordinates, 'lat');
        $lngs = array_column($coordinates, 'lng');

        if ($lats === [] || $lngs === []) {
            return false;
        }

        $minLat = min($lats);
        $maxLat = max($lats);
        $minLon = min($lngs);
        $maxLon = max($lngs);

        // Check if line segment passes through bounding box
        $lineMinLat = min($startLat, $endLat);
        $lineMaxLat = max($startLat, $endLat);
        $lineMinLon = min($startLon, $endLon);
        $lineMaxLon = max($startLon, $endLon);

        return ! ($lineMaxLat < $minLat || $lineMinLat > $maxLat ||
                 $lineMaxLon < $minLon || $lineMinLon > $maxLon);
    }

    /**
     * Calculate point to avoid geofence
     *
     * @return array<string, mixed>
     */
    protected function calculateAvoidancePoint(
        float $startLat,
        float $startLon,
        float $endLat,
        float $endLon,
        Geofence $geofence
    ): ?array {
        $coordinates = is_string($geofence->coordinates) ? json_decode($geofence->coordinates, true) : $geofence->coordinates;

        if (empty($coordinates)) {
            return null;
        }

        // Calculate geofence center
        $centerLat = array_sum(array_column($coordinates, 'lat')) / count($coordinates);
        $centerLon = array_sum(array_column($coordinates, 'lng')) / count($coordinates);

        // Calculate perpendicular offset point
        $midLat = ($startLat + $endLat) / 2;
        $midLon = ($startLon + $endLon) / 2;

        // Calculate vector from geofence center to route midpoint
        $vecLat = $midLat - $centerLat;
        $vecLon = $midLon - $centerLon;

        // Normalize and scale
        $magnitude = sqrt($vecLat * $vecLat + $vecLon * $vecLon);
        if ($magnitude > 0) {
            $vecLat = ($vecLat / $magnitude) * 0.01; // ~1km offset
            $vecLon = ($vecLon / $magnitude) * 0.01;
        }

        return [
            'lat' => $centerLat + $vecLat,
            'lon' => $centerLon + $vecLon,
        ];
    }

    /**
     * Calculate distance between two coordinates using Haversine formula
     */
    public function calculateDistance(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2
    ): float {
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
     * Calculate bearing between two coordinates
     */
    public function calculateBearing(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2
    ): float {
        $dLon = deg2rad($lon2 - $lon1);
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);

        $y = sin($dLon) * cos($lat2Rad);
        $x = cos($lat1Rad) * sin($lat2Rad) -
             sin($lat1Rad) * cos($lat2Rad) * cos($dLon);

        $bearing = rad2deg(atan2($y, $x));

        return fmod(($bearing + 360), 360);
    }

    /**
     * Find nearest fuel stations along route
     *
     * @param  array<string, mixed>  $waypoints
     * @return array<string, mixed>
     */
    public function findNearbyFuelStations(array $waypoints, float $maxDetourKm = 5): array
    {
        // This would query a fuel_stations table if we had one
        // For now, return empty array
        return [];
    }
}
