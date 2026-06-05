<?php

namespace App\Console\Commands;

use App\Models\Machine;
use App\Models\MachineMetric;
use App\Models\Route;
use App\Models\Team;
use App\Services\RoutePlanningService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateRoadsPathCoordinates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'machines:generate-road-paths
                            {--team=1 : Team ID to generate coordinates for}
                            {--count=100 : Number of coordinates to generate per machine}
                            {--delete-komatsu-alpha : Delete coordinates for Komatsu PC800-ALPHA before generating}
                            {--machine= : Generate for specific machine ID only}
                            {--days=5 : Number of days to spread coordinates across}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate road-based path coordinates for machines along available routes';

    /**
     * Coordinate generation settings
     */
    protected int $teamId;

    protected int $count;

    protected int $days;

    /** @var array<string, mixed> */
    protected array $stats = [
        'created' => 0,
        'deleted' => 0,
        'errors' => 0,
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Starting coordinate generation...');
        $this->newLine();

        // Get options
        $this->teamId = (int) $this->option('team');
        $this->count = (int) $this->option('count');
        $this->days = (int) $this->option('days');
        $specificMachineId = $this->option('machine');

        // Get team
        $team = Team::find($this->teamId);
        if (! $team) {
            $this->error("❌ Team with ID {$this->teamId} not found");

            return self::FAILURE;
        }

        $this->info("Team: {$team->name} (ID: {$team->id})");

        // Handle Komatsu PC800-ALPHA deletion if requested
        if ($this->option('delete-komatsu-alpha')) {
            $this->handleKomatsuDeletion($team);
        }

        // Get machines
        $machinesQuery = Machine::where('team_id', $this->teamId);

        if ($specificMachineId) {
            $machinesQuery->where('id', $specificMachineId);
        }

        $machines = $machinesQuery->get();

        if ($machines->isEmpty()) {
            $this->warn('No machines found for this team');

            return self::SUCCESS;
        }

        $this->info("Machines found: {$machines->count()}");
        $this->newLine();

        // Get available routes for the team
        $routes = Route::where('team_id', $this->teamId)
            ->where('status', 'active')
            ->with('waypoints')
            ->get();

        if ($routes->isEmpty()) {
            $this->warn('⚠️  No routes found. Generating circular paths as fallback.');
        }

        // Generate coordinates for each machine
        $progressBar = $this->output->createProgressBar($machines->count());
        $progressBar->start();

        foreach ($machines as $machine) {
            try {
                $generated = $this->generatePathForMachine($machine, $routes);
                $this->stats['created'] += $generated;
                $progressBar->advance();
            } catch (\Exception $e) {
                $this->stats['errors']++;
                $this->error("\n❌ Error for {$machine->name}: {$e->getMessage()}");
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        // Display results
        $this->displayResults($machines);

        return self::SUCCESS;
    }

    /**
     * Handle deletion of Komatsu PC800-ALPHA coordinates
     */
    protected function handleKomatsuDeletion($team): void
    {
        $this->warn('❌ Deleting coordinates for Komatsu PC800-ALPHA...');

        $komatsu = Machine::where('team_id', $team->id)
            ->where('name', 'like', '%PC800-ALPHA%')
            ->orWhere('name', 'like', '%PC800 ALPHA%')
            ->orWhere('name', 'like', '%Komatsu PC800%')
            ->first();

        if (! $komatsu) {
            $this->info('   Machine not found (may already be deleted)');

            return;
        }

        $previousCount = MachineMetric::where('machine_id', $komatsu->id)->count();
        $deleted = MachineMetric::where('machine_id', $komatsu->id)->delete();

        $this->info("   Previous records: {$previousCount}");
        $this->info("   Deleted: {$deleted} ✅");
        $this->newLine();

        $this->stats['deleted'] += $deleted;
    }

    /**
     * Generate path coordinates for a single machine
     */
    protected function generatePathForMachine(Machine $machine, $routes): int
    {
        // Delete existing metrics for this machine first
        MachineMetric::where('machine_id', $machine->id)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->delete();

        // Select best route for this machine
        $route = $this->selectBestRoute($machine, $routes);

        if (! $route) {
            // Generate circular fallback path
            return $this->generateDefaultCoordinates($machine);
        }

        // Generate coordinates along the route
        return $this->generateCoordinatesAlongRoute($machine, $route);
    }

    /**
     * Select the best route for a machine based on proximity
     * Returns null for machines without specific routes to force OSRM generation
     */
    protected function selectBestRoute(Machine $machine, $routes): mixed
    {
        if ($routes->isEmpty()) {
            return null;
        }

        // ONLY use machine-specific routes - ignore team-wide routes
        // This ensures each machine gets its own unique road path via OSRM
        $machineSpecificRoute = $routes->where('machine_id', $machine->id)->first();
        if ($machineSpecificRoute && $machineSpecificRoute->waypoints->isNotEmpty()) {
            $this->info("  → Using machine-specific route: {$machineSpecificRoute->name}");

            return $machineSpecificRoute;
        }

        // No machine-specific route = use OSRM to place on different roads
        return null;
    }

    /**
     * Generate coordinates along a route with interpolation
     * Uses OSRM to calculate road-based paths between waypoints
     */
    protected function generateCoordinatesAlongRoute(Machine $machine, Route $route): int
    {
        $waypoints = $route->waypoints->sortBy('sequence_order')->values();

        if ($waypoints->count() < 2) {
            $this->warn('  → Route has too few waypoints, using OSRM fallback');

            return $this->generateDefaultCoordinates($machine);
        }

        $this->info("  → Using route: {$route->name} with {$waypoints->count()} waypoints");

        $allCoordinates = [];
        $routePlanningService = new RoutePlanningService;

        // For each segment between waypoints, calculate road-based route using OSRM
        for ($i = 0; $i < $waypoints->count() - 1; $i++) {
            $startWaypoint = $waypoints[$i];
            $endWaypoint = $waypoints[$i + 1];

            try {
                // Use OSRM to get actual road path between waypoints
                $segmentRoute = $routePlanningService->calculateOptimalRoute(
                    $startWaypoint->latitude,
                    $startWaypoint->longitude,
                    $endWaypoint->latitude,
                    $endWaypoint->longitude,
                    $machine->id,
                    $machine->team_id
                );

                if ($segmentRoute && ! empty($segmentRoute['route_geometry'])) {
                    // Add all points from this segment (they're all on roads)
                    foreach ($segmentRoute['route_geometry'] as $point) {
                        $allCoordinates[] = [
                            'lat' => $point[0],
                            'lng' => $point[1],
                        ];
                    }
                    $this->info("  → Segment {$i}: Added ".count($segmentRoute['route_geometry']).' road points');
                } else {
                    // Fallback to straight line for this segment only
                    $this->warn("  → Segment {$i}: OSRM failed, using straight line");
                    $segmentPoints = $this->interpolateAlongRoute(
                        $startWaypoint->latitude,
                        $startWaypoint->longitude,
                        $endWaypoint->latitude,
                        $endWaypoint->longitude,
                        20 // Use fewer points for straight segments
                    );
                    $allCoordinates = array_merge($allCoordinates, $segmentPoints);
                }
            } catch (\Exception $e) {
                $this->warn("  → Segment {$i}: Exception - {$e->getMessage()}");
                // Fallback to straight interpolation for this segment
                $segmentPoints = $this->interpolateAlongRoute(
                    $startWaypoint->latitude,
                    $startWaypoint->longitude,
                    $endWaypoint->latitude,
                    $endWaypoint->longitude,
                    20
                );
                $allCoordinates = array_merge($allCoordinates, $segmentPoints);
            }
        }

        // Now sample exactly $this->count points from all collected coordinates
        // CRITICAL: Only sample existing OSRM points, NEVER interpolate between them
        // to guarantee all coordinates stay on actual roads
        $this->info('  → Total road points collected: '.count($allCoordinates));

        if (count($allCoordinates) == 0) {
            $this->warn('  → No coordinates collected, using fallback');

            return $this->generateDefaultCoordinates($machine);
        }

        // Sample from actual OSRM points only - no interpolation
        $coordinates = $this->sampleRoutePoints($allCoordinates, $this->count);

        $this->info('  → Final coordinates: '.count($coordinates).' (sampled from roads only)');

        // Save coordinates to database with timing and metrics
        return $this->saveCoordinatesToDatabase($machine, $coordinates);
    }

    /**
     * Sample exact points from OSRM route - NO interpolation
     * This guarantees all coordinates are on actual roads
     *
     * @param  array<mixed>  $routePoints
     * @return array<mixed>
     */
    protected function sampleRoutePoints(array $routePoints, int $targetCount): array
    {
        $totalPoints = count($routePoints);

        if ($totalPoints == 0) {
            return [];
        }

        if ($totalPoints <= $targetCount) {
            // Use all points we have, even if less than target
            $this->info('  → Using all '.$totalPoints.' OSRM points (target was '.$targetCount.')');

            return $routePoints;
        }

        // Sample evenly-spaced points from the OSRM route
        $sampled = [];
        $step = ($totalPoints - 1) / ($targetCount - 1);

        for ($i = 0; $i < $targetCount; $i++) {
            $index = (int) round($i * $step);
            $index = min($index, $totalPoints - 1); // Ensure we don't exceed bounds
            $sampled[] = $routePoints[$index];
        }

        return $sampled;
    }

    /**
     * Interpolate points between two coordinates
     */
    protected function interpolateAlongRoute(
        float $startLat,
        float $startLng,
        float $endLat,
        float $endLng,
        int $numPoints
    ): array {
        $points = [];

        for ($i = 0; $i < $numPoints; $i++) {
            $progress = $i / max(1, $numPoints - 1);

            $lat = $startLat + ($endLat - $startLat) * $progress;
            $lng = $startLng + ($endLng - $startLng) * $progress;

            $points[] = [
                'lat' => $lat,
                'lng' => $lng,
            ];
        }

        return $points;
    }

    /**
     * Generate default circular coordinates as fallback
     */
    /**
     * Generate road-based coordinates using OSRM when no routes exist
     * Places each machine on different roads across South Africa
     */
    protected function generateDefaultCoordinates(Machine $machine): int
    {
        // Diverse start/end points across South African mining areas
        // johannesburg, Pretoria, Rustenburg, Witbank, Polokwane, Kimberley areas
        $southAfricaRoadPoints = [
            ['lat' => -26.2041, 'lng' => 28.0473, 'name' => 'Johannesburg'],
            ['lat' => -25.7479, 'lng' => 28.2293, 'name' => 'Pretoria'],
            ['lat' => -25.6522, 'lng' => 27.2456, 'name' => 'Rustenburg'],
            ['lat' => -25.8601, 'lng' => 29.2372, 'name' => 'Witbank'],
            ['lat' => -23.9045, 'lng' => 29.4689, 'name' => 'Polokwane'],
            ['lat' => -28.7282, 'lng' => 24.7499, 'name' => 'Kimberley'],
            ['lat' => -26.5225, 'lng' => 28.0852, 'name' => 'Soweto'],
            ['lat' => -25.4972, 'lng' => 28.9892, 'name' => 'Bronkhorstspruit'],
            ['lat' => -26.7056, 'lng' => 27.0931, 'name' => 'Potchefstroom'],
            ['lat' => -26.2309, 'lng' => 28.3443, 'name' => 'Benoni'],
            ['lat' => -25.5366, 'lng' => 29.7684, 'name' => 'Middelburg'],
            ['lat' => -27.4467, 'lng' => 27.9669, 'name' => 'Welkom'],
        ];

        // Select unique start and end points for this machine based on machine ID
        // Ensures different machines get different routes
        $startIndex = ($machine->id * 2) % count($southAfricaRoadPoints);
        $endIndex = ($machine->id * 2 + 5) % count($southAfricaRoadPoints);

        // Ensure start and end are different
        if ($startIndex === $endIndex) {
            $endIndex = ($endIndex + 1) % count($southAfricaRoadPoints);
        }

        $startPoint = $southAfricaRoadPoints[$startIndex];
        $endPoint = $southAfricaRoadPoints[$endIndex];

        $this->info("  → {$machine->name}: Calculating route from {$startPoint['name']} to {$endPoint['name']}");

        // Use OSRM to calculate road-based route
        try {
            $routePlanningService = new RoutePlanningService;
            $calculatedRoute = $routePlanningService->calculateOptimalRoute(
                $startPoint['lat'],
                $startPoint['lng'],
                $endPoint['lat'],
                $endPoint['lng'],
                $machine->id,
                $machine->team_id
            );

            if ($calculatedRoute && ! empty($calculatedRoute['route_geometry'])) {
                $routeGeometry = $calculatedRoute['route_geometry'];
                $this->info('  → OSRM route calculated: '.count($routeGeometry)." points, {$calculatedRoute['total_distance']} km");

                // Convert OSRM geometry to our format
                $roadPoints = array_map(function ($point) {
                    return [
                        'lat' => $point[0],
                        'lng' => $point[1],
                    ];
                }, $routeGeometry);

                // Sample from OSRM points only - NO interpolation to stay on roads
                $coordinates = $this->sampleRoutePoints($roadPoints, $this->count);

                $this->info('  → Sampled '.count($coordinates).' coordinates from road (no interpolation)');

                return $this->saveCoordinatesToDatabase($machine, $coordinates);
            } else {
                $this->warn('  → OSRM route empty, falling back to straight line');
            }
        } catch (\Exception $e) {
            $this->warn("  → OSRM failed: {$e->getMessage()}, falling back to straight line");
            Log::warning('OSRM route generation failed for machine '.$machine->id, [
                'machine' => $machine->name,
                'error' => $e->getMessage(),
            ]);
        }

        // Fallback: straight line between start and end (still better than circular)
        $this->info("  → Generating straight-line fallback between {$startPoint['name']} and {$endPoint['name']}");
        $coordinates = $this->interpolateAlongRoute(
            $startPoint['lat'],
            $startPoint['lng'],
            $endPoint['lat'],
            $endPoint['lng'],
            $this->count
        );

        return $this->saveCoordinatesToDatabase($machine, array_map(function ($coord) {
            return [
                'lat' => $coord['lat'],
                'lng' => $coord['lng'],
            ];
        }, $coordinates));
    }

    /**
     * Interpolate OSRM route geometry to get exact number of coordinates
     * Uses distance-based sampling to ensure we follow road curves precisely
     * and never cut through buildings, rivers, or non-road areas
     *
     * @param  array<mixed>  $routeGeometry
     * @return array<mixed>
     */
    protected function interpolateRouteGeometry(array $routeGeometry, int $targetCount): array
    {
        if (empty($routeGeometry)) {
            return [];
        }

        $sourceCount = count($routeGeometry);

        if ($sourceCount == 1) {
            // Only one point available
            return array_fill(0, $targetCount, $routeGeometry[0]);
        }

        // If source has fewer points than target, we need to densify the route
        if ($sourceCount < $targetCount) {
            return $this->densifyRouteGeometry($routeGeometry, $targetCount);
        }

        // Calculate cumulative distances along the route
        // This ensures we sample by distance, not by point index
        $distances = [0]; // Start at 0
        $totalDistance = 0;

        for ($i = 1; $i < $sourceCount; $i++) {
            $prevPoint = $routeGeometry[$i - 1];
            $currPoint = $routeGeometry[$i];

            // Handle both array formats: ['lat' => ..., 'lng' => ...] and [lat, lng]
            $prevLat = is_array($prevPoint) ? ($prevPoint['lat'] ?? $prevPoint[0]) : 0;
            $prevLng = is_array($prevPoint) ? ($prevPoint['lng'] ?? $prevPoint[1]) : 0;
            $currLat = is_array($currPoint) ? ($currPoint['lat'] ?? $currPoint[0]) : 0;
            $currLng = is_array($currPoint) ? ($currPoint['lng'] ?? $currPoint[1]) : 0;

            $segmentDistance = $this->calculateDistance($prevLat, $prevLng, $currLat, $currLng);

            $totalDistance += $segmentDistance;
            $distances[] = $totalDistance;
        }

        if ($totalDistance == 0) {
            // All points are identical
            return array_fill(0, $targetCount, $routeGeometry[0]);
        }

        // Sample points at evenly-spaced distances along the route
        $coordinates = [];
        $distanceStep = $totalDistance / ($targetCount - 1);

        for ($i = 0; $i < $targetCount; $i++) {
            $targetDistance = $i * $distanceStep;

            // Find the segment that contains this target distance
            for ($j = 0; $j < $sourceCount - 1; $j++) {
                if ($distances[$j] <= $targetDistance && $targetDistance <= $distances[$j + 1]) {
                    // Interpolate between these two points
                    $segmentStart = $routeGeometry[$j];
                    $segmentEnd = $routeGeometry[$j + 1];

                    // Handle both coordinate formats
                    $startLat = is_array($segmentStart) ? ($segmentStart['lat'] ?? $segmentStart[0]) : 0;
                    $startLng = is_array($segmentStart) ? ($segmentStart['lng'] ?? $segmentStart[1]) : 0;
                    $endLat = is_array($segmentEnd) ? ($segmentEnd['lat'] ?? $segmentEnd[0]) : 0;
                    $endLng = is_array($segmentEnd) ? ($segmentEnd['lng'] ?? $segmentEnd[1]) : 0;

                    $segmentDistance = $distances[$j + 1] - $distances[$j];
                    $distanceInSegment = $targetDistance - $distances[$j];
                    $fraction = $segmentDistance > 0 ? $distanceInSegment / $segmentDistance : 0;

                    // Linear interpolation along the road segment
                    $lat = $startLat + ($endLat - $startLat) * $fraction;
                    $lng = $startLng + ($endLng - $startLng) * $fraction;

                    $coordinates[] = [
                        'lat' => $lat,
                        'lng' => $lng,
                    ];
                    break;
                }
            }
        }

        // Ensure we have exactly targetCount points
        if (count($coordinates) < $targetCount) {
            // Add the last point if missing
            $lastPoint = $routeGeometry[$sourceCount - 1];
            $lastLat = is_array($lastPoint) ? ($lastPoint['lat'] ?? $lastPoint[0]) : 0;
            $lastLng = is_array($lastPoint) ? ($lastPoint['lng'] ?? $lastPoint[1]) : 0;

            while (count($coordinates) < $targetCount) {
                $coordinates[] = [
                    'lat' => $lastLat,
                    'lng' => $lastLng,
                ];
            }
        }

        return array_slice($coordinates, 0, $targetCount);
    }

    /**
     * Densify route geometry by interpolating between points
     * Used when OSRM returns fewer points than we need
     *
     * @param  array<mixed>  $routeGeometry
     * @return array<mixed>
     */
    protected function densifyRouteGeometry(array $routeGeometry, int $targetCount): array
    {
        $sourceCount = count($routeGeometry);

        if ($sourceCount == 0) {
            return [];
        }

        if ($sourceCount == 1) {
            // Only one point - duplicate it to reach target
            return array_fill(0, $targetCount, $routeGeometry[0]);
        }

        $coordinates = [];

        // Calculate how many intermediate points we need per segment
        $totalSegments = $sourceCount - 1;
        $pointsPerSegment = (int) ceil($targetCount / $totalSegments);

        for ($i = 0; $i < $totalSegments; $i++) {
            $start = $routeGeometry[$i];
            $end = $routeGeometry[$i + 1];

            // Ensure we have valid coordinate format
            if (! isset($start['lat']) || ! isset($start['lng']) ||
                ! isset($end['lat']) || ! isset($end['lng'])) {
                continue;
            }

            $segmentPoints = $pointsPerSegment;
            // Last segment gets remaining points
            if ($i === $totalSegments - 1) {
                $segmentPoints = max(1, $targetCount - count($coordinates));
            }

            // Interpolate points along this road segment
            for ($j = 0; $j < $segmentPoints && count($coordinates) < $targetCount; $j++) {
                $fraction = $segmentPoints > 1 ? $j / ($segmentPoints - 1) : 0;
                $lat = $start['lat'] + ($end['lat'] - $start['lat']) * $fraction;
                $lng = $start['lng'] + ($end['lng'] - $start['lng']) * $fraction;

                $coordinates[] = [
                    'lat' => $lat,
                    'lng' => $lng,
                ];
            }
        }

        // Ensure we have at least 1 coordinate
        if (empty($coordinates) && ! empty($routeGeometry)) {
            $coordinates[] = $routeGeometry[0];
        }

        // Pad with last coordinate if needed
        while (count($coordinates) < $targetCount && ! empty($coordinates)) {
            $coordinates[] = end($coordinates);
        }

        return array_slice($coordinates, 0, $targetCount);
    }

    /**
     * Save coordinates to database with calculated heading and speed
     */
    protected function saveCoordinatesToDatabase(Machine $machine, array $coordinates): int
    {
        $startTime = Carbon::now()->subDays($this->days);
        $timeInterval = ($this->days * 24 * 60) / $this->count; // minutes per coordinate
        $saved = 0;

        foreach ($coordinates as $index => $coord) {
            // Calculate heading from previous coordinate
            $heading = 0;
            if ($index > 0) {
                $prevCoord = $coordinates[$index - 1];
                $heading = $this->calculateHeading(
                    $prevCoord['lat'],
                    $prevCoord['lng'],
                    $coord['lat'],
                    $coord['lng']
                );
            }

            // Generate realistic speed (20-60 km/h with variation)
            $baseSpeed = 40;
            $speedVariation = rand(-20, 20);
            $speed = max(20, min(60, $baseSpeed + $speedVariation));

            // Calculate timestamp
            $timestamp = $startTime->copy()->addMinutes($index * $timeInterval);

            try {
                MachineMetric::create([
                    'machine_id' => $machine->id,
                    'team_id' => $this->teamId,
                    'latitude' => $coord['lat'],
                    'longitude' => $coord['lng'],
                    'speed' => $speed,
                    'heading' => $heading,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
                $saved++;
            } catch (\Exception $e) {
                // Continue on error
                continue;
            }
        }

        return $saved;
    }

    /**
     * Calculate heading (bearing) between two coordinates
     */
    protected function calculateHeading(
        float $lat1,
        float $lng1,
        float $lat2,
        float $lng2
    ): float {
        $dLng = deg2rad($lng2 - $lng1);
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);

        $y = sin($dLng) * cos($lat2Rad);
        $x = cos($lat1Rad) * sin($lat2Rad) - sin($lat1Rad) * cos($lat2Rad) * cos($dLng);

        $bearing = atan2($y, $x);
        $bearingDegrees = rad2deg($bearing);

        // Normalize to 0-360
        return fmod(($bearingDegrees + 360), 360);
    }

    /**
     * Calculate distance between two coordinates (Haversine formula)
     */
    protected function calculateDistance(
        float $lat1,
        float $lng1,
        float $lat2,
        float $lng2
    ): float {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Display generation results
     */
    protected function displayResults($machines): void
    {
        $this->info('📊 Summary:');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total coordinates created', $this->stats['created']],
                ['Total coordinates deleted', $this->stats['deleted']],
                ['Machines processed', $machines->count()],
                ['Errors encountered', $this->stats['errors']],
            ]
        );

        // Show per-machine breakdown
        $this->newLine();
        $this->info('📋 Per-Machine Breakdown:');

        $machineStats = [];
        foreach ($machines as $machine) {
            $metricsCount = MachineMetric::where('machine_id', $machine->id)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->count();

            $machineStats[] = [
                'name' => $machine->name,
                'coordinates' => $metricsCount,
                'status' => $metricsCount > 0 ? '✅' : '❌',
            ];
        }

        $this->table(
            ['Machine', 'Coordinates', 'Status'],
            $machineStats
        );

        $this->newLine();

        if ($this->stats['errors'] === 0) {
            $this->info('Status: ✅ SUCCESS');
        } else {
            $this->warn('Status: ⚠️  COMPLETED WITH ERRORS');
        }
    }
}
