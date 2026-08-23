<?php

namespace App\Services;

use App\Models\Route;
use App\Support\Geo;
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
        float $endLon
    ): array {
        // Get road-based route from OSRM
        $roadRoute = $this->getOSRMRoute($startLon, $startLat, $endLon, $endLat);

        if ($roadRoute === null) {
            // Fallback to straight-line calculation if OSRM fails
            return $this->calculateStraightLineRoute($startLat, $startLon, $endLat, $endLon);
        }

        // Extract route geometry (array of [lat, lon] coordinates)
        $routeCoordinates = $roadRoute['geometry'];
        $totalDistance = $roadRoute['distance'] / 1000.0; // Convert meters to km
        $totalDuration = $roadRoute['duration'] / 60.0; // Convert seconds to minutes

        // Generate waypoints from the route geometry
        // Sample points along the route to avoid too many waypoints
        $waypointData = $this->sampleRouteWaypoints($routeCoordinates);

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
     * @return array{geometry: list<array{0: float, 1: float}>, distance: float, duration: float, steps: list<mixed>}|null
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

            if (! is_array($data) || ($data['code'] ?? null) !== 'Ok' || ! is_array($data['routes'] ?? null) || $data['routes'] === []) {
                Log::warning('OSRM returned no valid routes', ['data' => $data]);

                return null;
            }

            /** @psalm-suppress MixedAssignment, MixedArrayAccess */
            $route = $data['routes'][0];

            if (! is_array($route)) {
                return null;
            }

            // Extract geometry coordinates from GeoJSON, converting each
            // [lon, lat] pair to [lat, lon] and dropping malformed entries.
            /** @psalm-suppress MixedAssignment */
            $rawCoordinates = data_get($route, 'geometry.coordinates');
            $routeCoordinates = [];

            /** @psalm-suppress MixedAssignment */
            foreach (is_array($rawCoordinates) ? $rawCoordinates : [] as $coord) {
                if (is_array($coord) && is_numeric($coord[0] ?? null) && is_numeric($coord[1] ?? null)) {
                    $routeCoordinates[] = [(float) $coord[1], (float) $coord[0]];
                }
            }

            /** @psalm-suppress MixedAssignment */
            $steps = data_get($route, 'legs.0.steps');

            return [
                'geometry' => $routeCoordinates,
                'distance' => is_numeric($route['distance'] ?? null) ? (float) $route['distance'] : 0.0,
                'duration' => is_numeric($route['duration'] ?? null) ? (float) $route['duration'] : 0.0,
                'steps' => is_array($steps) ? array_values($steps) : [],
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
     * @param  list<array{0: float, 1: float}>  $routeCoordinates
     * @return list<array<string, mixed>>
     */
    protected function sampleRouteWaypoints(array $routeCoordinates): array
    {
        if ($routeCoordinates === []) {
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

            $distance = Geo::distanceKm($prevLat, $prevLon, $lat, $lon);

            $waypointData[] = [
                'sequence_order' => count($waypointData) + 1,
                'latitude' => $lat,
                'longitude' => $lon,
                'waypoint_type' => 'navigation',
                'name' => 'Waypoint '.(count($waypointData) + 1),
                'distance_from_previous' => round($distance, 2),
                'estimated_time_from_previous' => (int) (($distance / $this->avgSpeed) * 60.0),
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
        $totalDistance = Geo::distanceKm($startLat, $startLon, $endLat, $endLon);
        $estimatedTime = (int) (($totalDistance / $this->avgSpeed) * 60.0);
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
     * Great-circle distance between two coordinates in kilometres.
     * Thin delegate kept for external callers (RouteAdvisorAgent); the
     * single shared implementation lives in \App\Support\Geo.
     */
    public function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        return Geo::distanceKm($lat1, $lon1, $lat2, $lon2);
    }
}
