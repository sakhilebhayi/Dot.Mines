<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\AIAgent;
use App\Models\AIRecommendation;
use App\Models\Alert;
use App\Models\FuelTransaction;
use App\Models\Geofence;
use App\Models\Machine;
use App\Models\MachineMetric;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceSchedule;
use App\Models\MineArea;
use App\Models\MineAreaProduction;
use App\Models\Route;
use App\Models\Team;
use App\Models\User;
use App\Models\Waypoint;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ComprehensiveDataSeeder extends Seeder
{
    private Team $team;

    private $aiAgent;

    private array $machines = [];

    private array $mineAreas = [];

    private array $excavators = [];

    private array $haulers = [];

    private array $users = [];

    private array $allTeams = [];

    /**
     * Run the database seeds - Creates realistic, correlated mining operation data
     */
    public function run(): void
    {
        $this->command->info('Starting comprehensive multi-team data seeding...');

        // Define multiple teams to create
        $teamConfigs = $this->getTeamConfigurations();

        foreach ($teamConfigs as $config) {
            $this->command->info('');
            $this->command->info('═══════════════════════════════════════════');
            $this->command->info("Creating data for: {$config['name']}");
            $this->command->info('═══════════════════════════════════════════');

            // Reset arrays for this team
            $this->machines = [];
            $this->mineAreas = [];
            $this->excavators = [];
            $this->haulers = [];
            $this->users = [];

            // Step 1: Create team and users
            $this->createTeamAndUsers($config);

            // Step 2: Create mine areas (pits, stockpiles, dumps)
            $this->createMineAreas($config['areas']);

            // Step 3: Create machines (excavators, haulers, dozers, etc.)
            $this->createMachines($config['machines']);

            // Step 4: Assign machines to mine areas and excavators
            $this->assignMachines();

            // Step 5: Create geofences around mine areas
            $this->createGeofences();

            // Step 6: Create routes for haulers
            $this->createRoutes();

            // Step 7: Generate machine metrics (last 30 days)
            $this->generateMachineMetrics();

            // Step 8: Generate production data (correlated with machine activity)
            $this->generateProductionData();

            // Step 9: Create fuel transactions
            $this->generateFuelData();

            // Step 10: Create realistic alerts based on machine status
            $this->generateAlerts();

            // Step 11: Assign machines to operators
            $this->assignMachineOperators();

            // Step 12: Create maintenance schedules
            $this->createMaintenanceSchedules();

            // Step 13: Generate maintenance records
            $this->generateMaintenanceRecords();

            // Step 14: Create activity logs for users
            $this->generateActivityLogs();

            // Step 15: Generate AI recommendations
            $this->generateAIRecommendations();

            $this->allTeams[] = [
                'team' => $this->team,
                'users' => count($this->users),
                'machines' => count($this->machines),
                'areas' => count($this->mineAreas),
            ];
        }

        $this->command->info('');
        $this->command->info('Multi-team data seeding completed successfully.');
        $this->printSummary();
    }

    private function getTeamConfigurations(): array
    {
        return [
            [
                'name' => 'Platinum Mining Corporation',
                'domain' => 'platinummine.com',
                'base_lat' => -26.19,
                'base_lon' => 28.05,
                'users' => [
                    ['name' => 'John Anderson', 'email' => 'john@platinummine.com'],
                    ['name' => 'Sarah Williams', 'email' => 'sarah@platinummine.com'],
                    ['name' => 'Michael Chen', 'email' => 'michael@platinummine.com'],
                    ['name' => 'Emma Davis', 'email' => 'emma@platinummine.com'],
                ],
                'areas' => 5,
                'machines' => ['excavators' => 4, 'haulers' => 8, 'dozers' => 2, 'graders' => 2, 'support' => 2],
            ],
            [
                'name' => 'Gold Fields Mining Ltd',
                'domain' => 'goldfields.co.za',
                'base_lat' => -26.55,
                'base_lon' => 27.85,
                'users' => [
                    ['name' => 'David Thompson', 'email' => 'david@goldfields.co.za'],
                    ['name' => 'Lisa Martinez', 'email' => 'lisa@goldfields.co.za'],
                    ['name' => 'James Brown', 'email' => 'james@goldfields.co.za'],
                ],
                'areas' => 4,
                'machines' => ['excavators' => 3, 'haulers' => 6, 'dozers' => 2, 'graders' => 1, 'support' => 1],
            ],
            [
                'name' => 'Diamond Extraction Co',
                'domain' => 'diamondco.co.za',
                'base_lat' => -28.75,
                'base_lon' => 24.75,
                'users' => [
                    ['name' => 'Robert Wilson', 'email' => 'robert@diamondco.co.za'],
                    ['name' => 'Jennifer Taylor', 'email' => 'jennifer@diamondco.co.za'],
                    ['name' => 'Thomas Moore', 'email' => 'thomas@diamondco.co.za'],
                    ['name' => 'Patricia Johnson', 'email' => 'patricia@diamondco.co.za'],
                    ['name' => 'Daniel White', 'email' => 'daniel@diamondco.co.za'],
                ],
                'areas' => 6,
                'machines' => ['excavators' => 5, 'haulers' => 10, 'dozers' => 3, 'graders' => 2, 'support' => 2],
            ],
            [
                'name' => 'Coal Mining Solutions',
                'domain' => 'coalmining.co.za',
                'base_lat' => -25.85,
                'base_lon' => 29.15,
                'users' => [
                    ['name' => 'Mark Anderson', 'email' => 'mark@coalmining.co.za'],
                    ['name' => 'Linda Garcia', 'email' => 'linda@coalmining.co.za'],
                    ['name' => 'Kevin Martinez', 'email' => 'kevin@coalmining.co.za'],
                ],
                'areas' => 3,
                'machines' => ['excavators' => 2, 'haulers' => 5, 'dozers' => 1, 'graders' => 1, 'support' => 1],
            ],
        ];
    }

    private function createTeamAndUsers($config): void
    {
        $this->command->info('Creating team and users...');

        // Get first user for team ownership
        $firstUserEmail = $config['users'][0]['email'];
        $firstUser = User::where('email', $firstUserEmail)->first();

        // Create team
        $this->team = Team::firstOrCreate(
            ['name' => $config['name']],
            [
                'user_id' => $firstUser?->id ?? 1,
                'personal_team' => false,
            ]
        );

        // Create users
        foreach ($config['users'] as $index => $userData) {
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'email' => $userData['email'],
                    'password' => Hash::make('password'),
                ]
            );

            if (! $user->teams->contains($this->team->id)) {
                $user->teams()->attach($this->team->id);
            }

            $this->users[] = $user;

            $user->update(['current_team_id' => $this->team->id]);

            // Set first user as team owner
            if ($index === 0) {
                $this->team->update(['user_id' => $user->id]);
            }
        }

        $this->command->info('Created team and '.count($config['users']).' users');
    }

    private function createMineAreas($areaCount): void
    {
        $this->command->info('Creating mine areas...');

        // Get base coordinates from team config
        $teamLatLonMap = [
            'Platinum Mining Corporation' => [-25.8906, 28.2341],
            'Gold Fields Mining Ltd' => [-26.55, 27.85],
            'Diamond Extraction Co' => [-28.75, 24.75],
            'Coal Mining Solutions' => [-25.85, 29.15],
        ];

        [$baseLat, $baseLon] = $teamLatLonMap[$this->team->name] ?? [-26.0, 28.0];

        $areaTypes = [
            ['type' => 'pit', 'name' => 'North Pit', 'material' => 'Ore'],
            ['type' => 'pit', 'name' => 'South Pit', 'material' => 'Ore'],
            ['type' => 'pit', 'name' => 'East Pit', 'material' => 'Ore'],
            ['type' => 'stockpile', 'name' => 'Main Stockpile', 'material' => 'Stockpile'],
            ['type' => 'dump', 'name' => 'Waste Dump', 'material' => 'Waste'],
            ['type' => 'processing', 'name' => 'Processing Plant', 'material' => null],
        ];

        for ($i = 0; $i < $areaCount; $i++) {
            $template = $areaTypes[$i % count($areaTypes)];
            $number = $i > 0 && $template['type'] === 'pit' ? ' #'.($i + 1) : '';

            $latOffset = (($i % 3) - 1) * 0.003;
            $lonOffset = (floor($i / 3) - 1) * 0.003;

            $centerLat = $baseLat + $latOffset;
            $centerLon = $baseLon + $lonOffset;

            $areaData = [
                'team_id' => $this->team->id,
                'name' => $template['name'].$number,
                'description' => ucfirst($template['type']).' area for '.($template['material'] ?? 'operations'),
                'type' => $template['type'],
                'status' => 'active',
                'center_latitude' => $centerLat,
                'center_longitude' => $centerLon,
                'coordinates' => $this->generatePolygonCoordinates($centerLat, $centerLon, 0.0008),
                'area_sqm' => rand(5000, 15000),
            ];

            if ($template['type'] === 'pit') {
                $areaData['material_types'] = [$template['material']];
                $areaData['mining_targets'] = [
                    'daily' => rand(3000, 6000),
                    'weekly' => rand(20000, 40000),
                    'monthly' => rand(100000, 180000),
                    'yearly' => rand(1200000, 2000000),
                ];
            }

            $area = MineArea::create($areaData);
            $this->mineAreas[] = $area;
        }

        $this->command->info('Created '.count($this->mineAreas).' mine areas');
    }

    private function createMachines($machineConfig): void
    {
        $this->command->info('Creating fleet machines...');

        $manufacturers = [
            'excavator' => [
                ['name' => 'CAT', 'models' => ['390F', '6030', '374F'], 'capacity' => [2500, 3000, 2200], 'fuel' => [1350, 1800, 1200]],
                ['name' => 'Komatsu', 'models' => ['PC800', 'PC1250', 'PC650'], 'capacity' => [5200, 8000, 4500], 'fuel' => [2000, 2500, 1600]],
                ['name' => 'Volvo', 'models' => ['EC750E', 'EC950F'], 'capacity' => [4800, 6500], 'fuel' => [1900, 2200]],
            ],
            'hauler' => [
                ['name' => 'Volvo', 'models' => ['A45G', 'A60H'], 'capacity' => [45000, 60000], 'fuel' => [497, 625]],
                ['name' => 'Bell Equipment', 'models' => ['B40E', 'B50E'], 'capacity' => [40000, 50000], 'fuel' => [440, 550]],
                ['name' => 'CAT', 'models' => ['745', '770'], 'capacity' => [42000, 58000], 'fuel' => [455, 600]],
            ],
            'dozer' => [
                ['name' => 'CAT', 'models' => ['D8T', 'D10T2'], 'capacity' => [35000, 45000], 'fuel' => [757, 950]],
                ['name' => 'Komatsu', 'models' => ['D155AX', 'D375A'], 'capacity' => [32000, 42000], 'fuel' => [690, 850]],
            ],
            'grader' => [
                ['name' => 'CAT', 'models' => ['16M', '160M'], 'capacity' => [16000, 18000], 'fuel' => [478, 520]],
                ['name' => 'Volvo', 'models' => ['G990', 'G970'], 'capacity' => [16000, 15500], 'fuel' => [456, 440]],
            ],
            'support' => [
                ['name' => 'Toyota', 'models' => ['Hilux', 'Land Cruiser'], 'capacity' => [1000, 1200], 'fuel' => [80, 138]],
                ['name' => 'Ford', 'models' => ['Ranger', 'F-150'], 'capacity' => [1000, 1100], 'fuel' => [80, 98]],
            ],
        ];

        $machineNumber = 100;

        // Create excavators
        for ($i = 0; $i < $machineConfig['excavators']; $i++) {
            $mfg = $manufacturers['excavator'][array_rand($manufacturers['excavator'])];
            $modelIdx = array_rand($mfg['models']);

            $machine = Machine::create([
                'team_id' => $this->team->id,
                'name' => "{$mfg['name']} {$mfg['models'][$modelIdx]} #".(++$machineNumber),
                'machine_type' => 'excavator',
                'manufacturer' => $mfg['name'],
                'model' => $mfg['models'][$modelIdx],
                'year_of_manufacture' => rand(2020, 2023),
                'registration_number' => strtoupper(substr($mfg['name'], 0, 3)).'-'.$mfg['models'][$modelIdx].'-'.$machineNumber,
                'serial_number' => strtoupper($mfg['name'].$mfg['models'][$modelIdx]).rand(2020, 2023).$machineNumber,
                'capacity' => $mfg['capacity'][$modelIdx],
                'fuel_capacity' => $mfg['fuel'][$modelIdx],
                'hours_meter' => rand(1000, 5000) + (rand(0, 99) / 10),
                'status' => ['active', 'active', 'active', 'idle'][array_rand(['active', 'active', 'active', 'idle'])],
                'last_location_latitude' => $this->mineAreas[0]->center_latitude + (rand(-10, 10) / 10000),
                'last_location_longitude' => $this->mineAreas[0]->center_longitude + (rand(-10, 10) / 10000),
                'last_location_update' => now()->subMinutes(rand(1, 30)),
            ]);

            $this->machines[] = $machine;
            $this->excavators[] = $machine;
        }

        // Create haulers
        for ($i = 0; $i < $machineConfig['haulers']; $i++) {
            $mfg = $manufacturers['hauler'][array_rand($manufacturers['hauler'])];
            $modelIdx = array_rand($mfg['models']);

            $machine = Machine::create([
                'team_id' => $this->team->id,
                'name' => "{$mfg['name']} {$mfg['models'][$modelIdx]} #".(++$machineNumber),
                'machine_type' => 'articulated_hauler',
                'manufacturer' => $mfg['name'],
                'model' => $mfg['models'][$modelIdx],
                'year_of_manufacture' => rand(2020, 2023),
                'registration_number' => strtoupper(substr($mfg['name'], 0, 3)).'-'.$mfg['models'][$modelIdx].'-'.$machineNumber,
                'serial_number' => strtoupper($mfg['name'].$mfg['models'][$modelIdx]).rand(2020, 2023).$machineNumber,
                'capacity' => $mfg['capacity'][$modelIdx],
                'fuel_capacity' => $mfg['fuel'][$modelIdx],
                'hours_meter' => rand(1000, 5000) + (rand(0, 99) / 10),
                'status' => ['active', 'active', 'idle', 'maintenance'][array_rand(['active', 'active', 'idle', 'maintenance'])],
                'last_location_latitude' => $this->mineAreas[0]->center_latitude + (rand(-10, 10) / 10000),
                'last_location_longitude' => $this->mineAreas[0]->center_longitude + (rand(-10, 10) / 10000),
                'last_location_update' => now()->subMinutes(rand(1, 30)),
            ]);

            $this->machines[] = $machine;
            $this->haulers[] = $machine;
        }

        // Create dozers
        for ($i = 0; $i < $machineConfig['dozers']; $i++) {
            $mfg = $manufacturers['dozer'][array_rand($manufacturers['dozer'])];
            $modelIdx = array_rand($mfg['models']);

            $machine = Machine::create([
                'team_id' => $this->team->id,
                'name' => "{$mfg['name']} {$mfg['models'][$modelIdx]} #".(++$machineNumber),
                'machine_type' => 'dozer',
                'manufacturer' => $mfg['name'],
                'model' => $mfg['models'][$modelIdx],
                'year_of_manufacture' => rand(2020, 2023),
                'registration_number' => strtoupper(substr($mfg['name'], 0, 3)).'-'.$mfg['models'][$modelIdx].'-'.$machineNumber,
                'serial_number' => strtoupper($mfg['name'].$mfg['models'][$modelIdx]).rand(2020, 2023).$machineNumber,
                'capacity' => $mfg['capacity'][$modelIdx],
                'fuel_capacity' => $mfg['fuel'][$modelIdx],
                'hours_meter' => rand(1000, 5000) + (rand(0, 99) / 10),
                'status' => 'active',
                'last_location_latitude' => $this->mineAreas[0]->center_latitude + (rand(-10, 10) / 10000),
                'last_location_longitude' => $this->mineAreas[0]->center_longitude + (rand(-10, 10) / 10000),
                'last_location_update' => now()->subMinutes(rand(1, 30)),
            ]);

            $this->machines[] = $machine;
        }

        // Create graders
        for ($i = 0; $i < $machineConfig['graders']; $i++) {
            $mfg = $manufacturers['grader'][array_rand($manufacturers['grader'])];
            $modelIdx = array_rand($mfg['models']);

            $machine = Machine::create([
                'team_id' => $this->team->id,
                'name' => "{$mfg['name']} {$mfg['models'][$modelIdx]} #".(++$machineNumber),
                'machine_type' => 'grader',
                'manufacturer' => $mfg['name'],
                'model' => $mfg['models'][$modelIdx],
                'year_of_manufacture' => rand(2020, 2023),
                'registration_number' => strtoupper(substr($mfg['name'], 0, 3)).'-'.$mfg['models'][$modelIdx].'-'.$machineNumber,
                'serial_number' => strtoupper($mfg['name'].$mfg['models'][$modelIdx]).rand(2020, 2023).$machineNumber,
                'capacity' => $mfg['capacity'][$modelIdx],
                'fuel_capacity' => $mfg['fuel'][$modelIdx],
                'hours_meter' => rand(1000, 5000) + (rand(0, 99) / 10),
                'status' => 'active',
                'last_location_latitude' => $this->mineAreas[0]->center_latitude + (rand(-10, 10) / 10000),
                'last_location_longitude' => $this->mineAreas[0]->center_longitude + (rand(-10, 10) / 10000),
                'last_location_update' => now()->subMinutes(rand(1, 30)),
            ]);

            $this->machines[] = $machine;
        }

        // Create support vehicles
        for ($i = 0; $i < $machineConfig['support']; $i++) {
            $mfg = $manufacturers['support'][array_rand($manufacturers['support'])];
            $modelIdx = array_rand($mfg['models']);

            $machine = Machine::create([
                'team_id' => $this->team->id,
                'name' => "{$mfg['name']} {$mfg['models'][$modelIdx]} #".(++$machineNumber),
                'machine_type' => 'support_vehicle',
                'manufacturer' => $mfg['name'],
                'model' => $mfg['models'][$modelIdx],
                'year_of_manufacture' => rand(2020, 2023),
                'registration_number' => strtoupper(substr($mfg['name'], 0, 3)).'-'.substr($mfg['models'][$modelIdx], 0, 3).'-'.$machineNumber,
                'serial_number' => strtoupper($mfg['name'].str_replace(' ', '', $mfg['models'][$modelIdx])).rand(2020, 2023).$machineNumber,
                'capacity' => $mfg['capacity'][$modelIdx],
                'fuel_capacity' => $mfg['fuel'][$modelIdx],
                'hours_meter' => rand(500, 2000) + (rand(0, 99) / 10),
                'status' => 'active',
                'last_location_latitude' => $this->mineAreas[0]->center_latitude + (rand(-10, 10) / 10000),
                'last_location_longitude' => $this->mineAreas[0]->center_longitude + (rand(-10, 10) / 10000),
                'last_location_update' => now()->subMinutes(rand(1, 30)),
            ]);

            $this->machines[] = $machine;
        }

        $this->command->info('Created '.count($this->machines).' machines');
    }

    private function assignMachines(): void
    {
        $this->command->info('Assigning machines to mine areas...');

        // Assign excavators to pits
        $pits = array_filter($this->mineAreas, fn ($area) => $area->type === 'pit');

        foreach ($this->excavators as $index => $excavator) {
            $pit = $pits[array_rand($pits)];
            $excavator->update(['mine_area_id' => $pit->id]);
            $excavator->mineAreas()->attach($pit->id, [
                'assigned_at' => now()->subDays(rand(10, 90)),
                'notes' => 'Primary excavator for this pit',
            ]);
        }

        // Assign haulers to excavators and mine areas
        foreach ($this->haulers as $index => $hauler) {
            if ($hauler->status === 'active' && ! empty($this->excavators)) {
                $excavator = $this->excavators[array_rand($this->excavators)];
                $hauler->update([
                    'excavator_id' => $excavator->id,
                    'mine_area_id' => $excavator->mine_area_id,
                    'assigned_to_excavator_at' => now()->subDays(rand(5, 60)),
                ]);

                if ($excavator->mineArea) {
                    $hauler->mineAreas()->attach($excavator->mine_area_id, [
                        'assigned_at' => now()->subDays(rand(5, 60)),
                        'notes' => "Hauling from excavator {$excavator->name}",
                    ]);
                }
            }
        }

        $this->command->info('Assigned machines to mine areas');
    }

    private function createGeofences(): void
    {
        $this->command->info('Creating geofences...');

        foreach ($this->mineAreas as $area) {
            $geofence = Geofence::create([
                'team_id' => $this->team->id,
                'mine_area_id' => $area->id,
                'name' => "{$area->name} Boundary",
                'description' => "Safety boundary for {$area->name}",
                'type' => $area->type,
                'center_latitude' => $area->center_latitude,
                'center_longitude' => $area->center_longitude,
                'coordinates' => $area->coordinates,
                'area_sqm' => $area->area_sqm,
                'perimeter_m' => $area->perimeter_m,
                'status' => 'active',
            ]);
        }

        $this->command->info('Created geofences for all mine areas');
    }

    private function createRoutes(): void
    {
        $this->command->info('Creating routes for haulers...');

        $routeCount = 0;

        foreach ($this->haulers as $hauler) {
            if ($hauler->status !== 'active' || ! $hauler->excavator) {
                continue;
            }

            $loadingPoint = $hauler->mineArea;
            $dumpPoint = array_values(array_filter(
                $this->mineAreas,
                fn ($area) => in_array($area->type, ['stockpile', 'dump'])
            ))[0] ?? null;

            if (! $loadingPoint || ! $dumpPoint) {
                continue;
            }

            $route = Route::create([
                'team_id' => $this->team->id,
                'machine_id' => $hauler->id,
                'mine_area_id' => $loadingPoint->id,
                'name' => "{$loadingPoint->name} to {$dumpPoint->name}",
                'description' => "Hauling route for {$hauler->name}",
                'start_latitude' => $loadingPoint->center_latitude,
                'start_longitude' => $loadingPoint->center_longitude,
                'end_latitude' => $dumpPoint->center_latitude,
                'end_longitude' => $dumpPoint->center_longitude,
                'total_distance' => rand(800, 2500) / 1000, // 0.8-2.5 km
                'estimated_time' => rand(8, 15), // minutes
                'estimated_fuel' => rand(5, 12), // liters
                'route_type' => 'optimal',
                'speed_limit' => rand(30, 50),
                'status' => 'active',
            ]);

            // Create 2-4 waypoints
            $waypointCount = rand(2, 4);
            for ($i = 1; $i <= $waypointCount; $i++) {
                $fraction = $i / ($waypointCount + 1);
                Waypoint::create([
                    'route_id' => $route->id,
                    'latitude' => $loadingPoint->center_latitude +
                                 ($dumpPoint->center_latitude - $loadingPoint->center_latitude) * $fraction,
                    'longitude' => $loadingPoint->center_longitude +
                                  ($dumpPoint->center_longitude - $loadingPoint->center_longitude) * $fraction,
                    'sequence_order' => $i,
                    'waypoint_type' => 'standard',
                ]);
            }

            $routeCount++;
        }

        $this->command->info("Created $routeCount routes");
    }

    private function generateMachineMetrics(): void
    {
        $this->command->info('Generating machine metrics (30 days)...');

        $metricsCount = 0;

        foreach ($this->machines as $machine) {
            // Generate metrics for last 30 days
            for ($day = 30; $day >= 0; $day--) {
                $date = now()->subDays($day);

                // Skip some days randomly for maintenance machines
                if ($machine->status === 'maintenance' && rand(1, 3) === 1) {
                    continue;
                }

                // Generate 3-8 metrics per day (one every few hours)
                $metricsPerDay = $machine->status === 'active' ? rand(6, 12) : rand(2, 5);

                for ($i = 0; $i < $metricsPerDay; $i++) {
                    $timestamp = $date->copy()->addHours($i * (24 / $metricsPerDay));

                    // Base metrics on machine type
                    $speed = 0;
                    $fuelLevel = rand(20, 95);
                    $temperature = rand(75, 95);

                    if ($machine->status === 'active') {
                        $speed = match ($machine->machine_type) {
                            'excavator' => 0, // Excavators don't move much
                            'articulated_hauler' => rand(15, 45),
                            'dozer' => rand(5, 15),
                            'grader' => rand(8, 20),
                            'support_vehicle' => rand(40, 80),
                            default => 0,
                        };
                    }

                    MachineMetric::create([
                        'team_id' => $this->team->id,
                        'machine_id' => $machine->id,
                        'latitude' => $machine->last_location_latitude + (rand(-20, 20) / 100000),
                        'longitude' => $machine->last_location_longitude + (rand(-20, 20) / 100000),
                        'speed' => $speed,
                        'fuel_level' => $fuelLevel,
                        'engine_temperature' => $temperature,
                        'total_hours' => $machine->hours_meter - ((30 - $day) * 8 / 30),
                        'idle_hours' => rand(0, 2) / 10,
                        'operating_hours' => rand(6, 9) / 10,
                        'load_weight' => $machine->machine_type === 'articulated_hauler' ?
                                       rand(25000, (int) $machine->capacity) : null,
                        'payload_capacity_used' => $machine->machine_type === 'articulated_hauler' ?
                                                   rand(60, 95) : null,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ]);

                    $metricsCount++;
                }
            }
        }

        $this->command->info("Generated $metricsCount machine metrics");
    }

    private function generateProductionData(): void
    {
        $this->command->info('Generating production data...');

        $productionCount = 0;

        // Get only pit mine areas
        $pits = array_filter($this->mineAreas, fn ($area) => $area->type === 'pit');

        // Generate production for last 30 days
        for ($day = 30; $day >= 0; $day--) {
            $date = now()->subDays($day);

            foreach ($pits as $pit) {
                // Get machines assigned to this pit
                $pitMachines = array_filter(
                    $this->machines,
                    fn ($m) => $m->mine_area_id === $pit->id && $m->status === 'active'
                );

                if (empty($pitMachines)) {
                    continue;
                }

                // Calculate realistic production based on number of active machines
                $machineCount = count($pitMachines);
                $baseTonnage = $machineCount * rand(150, 300); // Per machine per day

                // Get material types for this pit
                $materials = $pit->material_types ?? ['Platinum Ore'];
                $material = $materials[array_rand($materials)];

                $loads = $machineCount * rand(20, 40);
                $cycles = $machineCount * rand(15, 30);
                $tonnage = $baseTonnage + rand(-200, 200);
                $bcm = $tonnage * rand(0.7, 0.9); // BCM typically less than tonnage

                MineAreaProduction::create([
                    'mine_area_id' => $pit->id,
                    'recorded_date' => $date->toDateString(),
                    'material_type' => $material,
                    'tonnage' => $tonnage,
                    'volume_cubic_m' => $bcm * 1.3,
                    'loads' => $loads,
                    'cycles' => $cycles,
                    'bcm' => $bcm,
                    'machines_used' => array_map(fn ($m) => $m->id, $pitMachines),
                    'status' => 'recorded',
                    'created_at' => $date,
                    'updated_at' => $date,
                ]);

                $productionCount++;
            }
        }

        $this->command->info("Generated $productionCount production records");
    }

    private function generateFuelData(): void
    {
        $this->command->info('Generating fuel transactions...');

        $transactionCount = 0;

        foreach ($this->machines as $machine) {
            // Generate 3-8 refueling events over past 30 days
            $refuelCount = rand(3, 8);

            for ($i = 0; $i < $refuelCount; $i++) {
                $daysAgo = rand(1, 30);
                $timestamp = now()->subDays($daysAgo)->subHours(rand(0, 23));

                $litersFilled = rand(
                    (int) ($machine->fuel_capacity * 0.4),
                    (int) ($machine->fuel_capacity * 0.9)
                );

                $unitPrice = rand(1850, 2150) / 100; // R18.50 - R21.50
                $machineHours = $machine->hours_meter - (30 - $daysAgo) * 8;

                FuelTransaction::create([
                    'team_id' => $this->team->id,
                    'machine_id' => $machine->id,
                    'user_id' => $this->users[array_rand($this->users)]->id,
                    'transaction_type' => 'refuel',
                    'quantity_liters' => $litersFilled,
                    'unit_price' => $unitPrice,
                    'total_cost' => $litersFilled * $unitPrice,
                    'fuel_type' => 'diesel',
                    'transaction_date' => $timestamp,
                    'odometer_reading' => $machineHours * 25, // Approximate km from hours
                    'machine_hours' => $machineHours,
                    'supplier' => 'Mine Fuel Depot',
                    'notes' => 'Routine refueling',
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);

                $transactionCount++;
            }
        }

        $this->command->info("Generated $transactionCount fuel transactions");
    }

    private function generateAlerts(): void
    {
        $this->command->info('Generating realistic alerts...');

        $alertCount = 0;

        foreach ($this->machines as $machine) {
            // Machines in maintenance should have maintenance alerts
            if ($machine->status === 'maintenance') {
                Alert::create([
                    'team_id' => $this->team->id,
                    'machine_id' => $machine->id,
                    'type' => 'maintenance',
                    'title' => 'Scheduled Maintenance Required',
                    'description' => "{$machine->name} requires scheduled maintenance. Hours: {$machine->hours_meter}",
                    'priority' => 'high',
                    'status' => 'active',
                    'triggered_at' => now()->subDays(rand(1, 5)),
                    'metadata' => [
                        'hours' => $machine->hours_meter,
                        'maintenance_type' => 'scheduled',
                    ],
                ]);
                $alertCount++;
            }

            // Generate some random operational alerts for active machines
            if ($machine->status === 'active' && rand(1, 3) === 1) {
                $alertTypes = [
                    [
                        'type' => 'temperature',
                        'title' => 'High Engine Temperature',
                        'description' => "{$machine->name} engine temperature exceeds normal operating range.",
                        'priority' => 'medium',
                    ],
                    [
                        'type' => 'fuel',
                        'title' => 'Low Fuel Level',
                        'description' => "{$machine->name} fuel level below 25%. Refuel soon.",
                        'priority' => 'medium',
                    ],
                    [
                        'type' => 'speed_violation',
                        'title' => 'Speed Limit Exceeded',
                        'description' => "{$machine->name} exceeded route speed limit.",
                        'priority' => 'low',
                    ],
                ];

                $alert = $alertTypes[array_rand($alertTypes)];
                $alert['team_id'] = $this->team->id;
                $alert['machine_id'] = $machine->id;
                $alert['status'] = rand(1, 4) === 1 ? 'resolved' : 'active';
                $alert['triggered_at'] = now()->subHours(rand(1, 48));

                if ($alert['status'] === 'resolved') {
                    $alert['resolved_at'] = now()->subHours(rand(1, 24));
                }

                Alert::create($alert);
                $alertCount++;
            }

            // Idle machines might have idle alerts
            if ($machine->status === 'idle') {
                Alert::create([
                    'team_id' => $this->team->id,
                    'machine_id' => $machine->id,
                    'type' => 'machine_idle',
                    'title' => 'Machine Idle in Production',
                    'description' => "{$machine->name} has been idle for extended period.",
                    'priority' => 'medium',
                    'status' => 'active',
                    'triggered_at' => now()->subHours(rand(2, 24)),
                    'metadata' => [
                        'idle_duration' => rand(30, 120),
                    ],
                ]);
                $alertCount++;
            }
        }

        $this->command->info("Generated $alertCount alerts");
    }

    private function generateAIRecommendations(): void
    {
        $this->command->info('Generating AI recommendations...');

        // Create an AI agent if one doesn't exist
        $this->aiAgent = AIAgent::firstOrCreate(
            ['name' => 'Mining Operations Optimizer'],
            [
                'type' => 'general',
                'description' => 'Provides comprehensive optimization recommendations for mining operations',
                'status' => 'active',
                'capabilities' => ['fleet_optimization', 'route_analysis', 'fuel_management', 'production_optimization'],
                'accuracy_score' => 0.85,
                'configuration' => [],
            ]
        );

        $recommendations = [
            [
                'category' => 'fleet',
                'title' => 'Optimize Hauler Distribution',
                'description' => 'North Pit #1 shows 18% higher productivity. Reassign 2 haulers from South Pit to increase overall fleet efficiency by estimated 12%.',
                'priority' => 'high',
                'status' => 'pending',
                'estimated_savings' => 45000,
                'confidence_score' => 0.87,
            ],
            [
                'category' => 'fuel',
                'title' => 'Reduce Idle Time',
                'description' => 'Volvo A45G #206 shows excessive idle time (22%). Implement idle shutdown protocol to save approximately R15,000/month in fuel costs.',
                'priority' => 'medium',
                'status' => 'pending',
                'estimated_savings' => 15000,
                'confidence_score' => 0.92,
            ],
            [
                'category' => 'maintenance',
                'title' => 'Predictive Maintenance Alert',
                'description' => 'CAT 390F #101 engine temperature trending upward. Schedule inspection before reaching critical hours to prevent costly breakdown.',
                'priority' => 'high',
                'status' => 'pending',
                'estimated_savings' => 85000,
                'confidence_score' => 0.79,
            ],
            [
                'category' => 'route',
                'title' => 'Alternative Route Suggestion',
                'description' => 'Route from North Pit to East Stockpile can be optimized. New route reduces distance by 340m, saving 8 minutes per cycle.',
                'priority' => 'medium',
                'status' => 'pending',
                'estimated_savings' => 22000,
                'confidence_score' => 0.85,
            ],
            [
                'category' => 'production',
                'title' => 'Shift Optimization',
                'description' => 'Morning shift (06:00-14:00) shows 23% higher productivity than afternoon shift. Consider crew cross-training to balance performance.',
                'priority' => 'low',
                'status' => 'pending',
                'estimated_savings' => 18000,
                'confidence_score' => 0.74,
            ],
        ];

        foreach ($recommendations as $rec) {
            $rec['team_id'] = $this->team->id;
            $rec['ai_agent_id'] = $this->aiAgent->id;
            AIRecommendation::create($rec);
        }

        $this->command->info('Generated '.count($recommendations).' AI recommendations');
    }

    private function assignMachineOperators(): void
    {
        $this->command->info('Assigning machine operators...');

        $operatorCount = 0;

        // Assign primary operators to machines
        foreach ($this->machines as $machine) {
            $operator = $this->users[array_rand($this->users)];

            // Update machine notes with operator assignment
            $currentNotes = $machine->notes ?? '';
            $operatorNote = "Primary Operator: {$operator->name}";

            $machine->update([
                'notes' => $currentNotes ? $currentNotes."\n".$operatorNote : $operatorNote,
            ]);

            $operatorCount++;
        }

        $this->command->info("Assigned {$operatorCount} operators to machines");
    }

    private function createMaintenanceSchedules(): void
    {
        $this->command->info('Creating maintenance schedules...');

        $scheduleCount = 0;

        $maintenanceTypes = [
            [
                'type' => 'preventive',
                'title' => 'Engine Oil & Filter Change',
                'interval_hours' => 250,
                'estimated_cost' => 3500,
                'estimated_duration_hours' => 2,
                'parts' => ['Engine Oil (20L)', 'Oil Filter', 'Air Filter'],
            ],
            [
                'type' => 'preventive',
                'title' => 'Hydraulic System Check',
                'interval_hours' => 500,
                'estimated_cost' => 2500,
                'estimated_duration_hours' => 1.5,
                'parts' => ['Hydraulic Oil', 'Filter Kit'],
            ],
            [
                'type' => 'preventive',
                'title' => 'Tire/Track Inspection',
                'interval_hours' => 100,
                'estimated_cost' => 500,
                'estimated_duration_hours' => 0.5,
                'parts' => [],
            ],
            [
                'type' => 'inspection',
                'title' => 'Safety System Inspection',
                'interval_days' => 30,
                'estimated_cost' => 800,
                'estimated_duration_hours' => 1,
                'parts' => [],
            ],
        ];

        foreach ($this->machines as $machine) {
            foreach ($maintenanceTypes as $maintenance) {
                $lastServiceHours = $machine->hours_meter - rand(50, 200);
                $nextServiceHours = $lastServiceHours + ($maintenance['interval_hours'] ?? 0);

                $lastServiceDate = now()->subDays(rand(5, 25));
                $nextServiceDate = $lastServiceDate->copy()->addDays($maintenance['interval_days'] ?? 30);

                MaintenanceSchedule::create([
                    'team_id' => $this->team->id,
                    'machine_id' => $machine->id,
                    'maintenance_type' => $maintenance['type'],
                    'title' => $maintenance['title'],
                    'description' => "Regular {$maintenance['title']} for {$machine->name}",
                    'schedule_type' => isset($maintenance['interval_hours']) ? 'hours' : 'calendar',
                    'interval_hours' => $maintenance['interval_hours'] ?? null,
                    'interval_days' => $maintenance['interval_days'] ?? null,
                    'last_service_hours' => $lastServiceHours,
                    'last_service_date' => $lastServiceDate,
                    'next_service_hours' => $nextServiceHours,
                    'next_service_date' => $nextServiceDate,
                    'priority' => $nextServiceHours < $machine->hours_meter ? 'high' : 'medium',
                    'status' => 'active',
                    'estimated_cost' => $maintenance['estimated_cost'],
                    'estimated_duration_hours' => $maintenance['estimated_duration_hours'],
                    'required_parts' => $maintenance['parts'],
                    'auto_generate_work_order' => true,
                ]);

                $scheduleCount++;
            }
        }

        $this->command->info("Created {$scheduleCount} maintenance schedules");
    }

    private function generateMaintenanceRecords(): void
    {
        $this->command->info('Generating maintenance records...');

        $recordCount = 0;

        $maintenanceTypes = ['preventive', 'corrective', 'inspection', 'breakdown'];
        $statuses = ['completed', 'completed', 'completed', 'in_progress', 'scheduled'];

        foreach ($this->machines as $machine) {
            // Generate 3-7 historical maintenance records
            $records = rand(3, 7);

            for ($i = 0; $i < $records; $i++) {
                $daysAgo = rand(1, 90);
                $assignedToUser = $this->users[array_rand($this->users)];
                $completedByUser = $this->users[array_rand($this->users)];
                $status = $statuses[array_rand($statuses)];

                $scheduledDate = now()->subDays($daysAgo);
                $startedAt = $status !== 'scheduled' ? $scheduledDate->copy()->addHours(rand(1, 3)) : null;
                $completedAt = $status === 'completed' ? $startedAt->copy()->addHours(rand(1, 8)) : null;

                $laborHours = $completedAt ? $completedAt->diffInHours($startedAt) + (rand(0, 30) / 10) : rand(2, 8);
                $laborCost = $laborHours * rand(350, 500); // R350-R500 per hour
                $partsCost = rand(500, 5000);

                MaintenanceRecord::create([
                    'team_id' => $this->team->id,
                    'machine_id' => $machine->id,
                    'maintenance_type' => $maintenanceTypes[array_rand($maintenanceTypes)],
                    'title' => $this->getRandomMaintenanceTitle(),
                    'description' => $this->getRandomMaintenanceDescription(),
                    'work_performed' => $status === 'completed' ? $this->getRandomWorkPerformed() : null,
                    'status' => $status,
                    'priority' => ['low', 'medium', 'high'][array_rand(['low', 'medium', 'high'])],
                    'scheduled_date' => $scheduledDate,
                    'started_at' => $startedAt,
                    'completed_at' => $completedAt,
                    'assigned_to' => $assignedToUser->id,
                    'completed_by' => $status === 'completed' ? $completedByUser->id : null,
                    'labor_hours' => $laborHours,
                    'labor_cost' => $laborCost,
                    'parts_cost' => $partsCost,
                    'total_cost' => $laborCost + $partsCost,
                    'parts_used' => $this->getRandomPartsUsed(),
                    'odometer_reading' => $machine->hours_meter * 25,
                    'hour_meter_reading' => $machine->hours_meter - rand(10, 100),
                    'technician_notes' => $status === 'completed' ? 'Work completed successfully. Machine tested and operational.' : 'Scheduled maintenance',
                    'machine_operational' => $status === 'completed',
                ]);

                $recordCount++;
            }
        }

        $this->command->info("Generated {$recordCount} maintenance records");
    }

    private function generateActivityLogs(): void
    {
        $this->command->info('Generating user activity logs...');

        $logCount = 0;

        $activities = [
            ['action' => 'login', 'description' => 'User logged into system'],
            ['action' => 'view_dashboard', 'description' => 'Viewed main dashboard'],
            ['action' => 'view_fleet', 'description' => 'Viewed fleet management page'],
            ['action' => 'view_machine', 'description' => 'Viewed machine details'],
            ['action' => 'view_production', 'description' => 'Viewed production dashboard'],
            ['action' => 'create_alert', 'description' => 'Created maintenance alert'],
            ['action' => 'update_machine', 'description' => 'Updated machine information'],
            ['action' => 'export_report', 'description' => 'Exported production report'],
            ['action' => 'view_map', 'description' => 'Viewed live tracking map'],
            ['action' => 'create_route', 'description' => 'Created new route'],
        ];

        foreach ($this->users as $user) {
            // Generate 15-30 activity logs per user over past 30 days
            $activityCount = rand(15, 30);

            for ($i = 0; $i < $activityCount; $i++) {
                $activity = $activities[array_rand($activities)];
                $daysAgo = rand(0, 30);

                ActivityLog::create([
                    'team_id' => $this->team->id,
                    'user_id' => $user->id,
                    'action' => $activity['action'],
                    'description' => $activity['description'],
                    'created_at' => now()->subDays($daysAgo)->subHours(rand(0, 23)),
                ]);

                $logCount++;
            }
        }

        $this->command->info("Generated {$logCount} activity logs");
    }

    private function getRandomMaintenanceTitle(): string
    {
        $titles = [
            'Engine Oil Change',
            'Hydraulic System Service',
            'Brake System Inspection',
            'Tire Replacement',
            'Filter Replacement',
            'Electrical System Check',
            'Cooling System Service',
            'Transmission Service',
            'Safety System Inspection',
            'General Inspection',
            'Emergency Repair',
            'Component Replacement',
        ];

        return $titles[array_rand($titles)];
    }

    private function getRandomMaintenanceDescription(): string
    {
        $descriptions = [
            'Routine preventive maintenance as per schedule',
            'Responding to operator-reported issue',
            'Scheduled inspection due to hours threshold',
            'Follow-up service from previous maintenance',
            'Emergency breakdown repair',
            'Manufacturer-recommended service interval',
            'Safety compliance inspection',
            'Pre-season maintenance check',
        ];

        return $descriptions[array_rand($descriptions)];
    }

    private function getRandomWorkPerformed(): string
    {
        $work = [
            'Replaced engine oil and filters. Inspected belts and hoses. All systems operational.',
            'Serviced hydraulic system. Replaced filters and topped up fluid. Pressure tested.',
            'Inspected brake system. Replaced worn pads. Adjusted brake balance.',
            'Replaced damaged tire. Checked alignment and torque specifications.',
            'Conducted full safety inspection. All systems within specification.',
            'Repaired electrical fault. Replaced damaged wiring harness.',
            'Serviced cooling system. Replaced thermostat and coolant.',
            'Diagnosed and repaired transmission issue. Replaced clutch assembly.',
        ];

        return $work[array_rand($work)];
    }

    private function getRandomPartsUsed(): array
    {
        $partsSets = [
            [
                ['name' => 'Engine Oil Filter', 'part_number' => 'EO-'.rand(1000, 9999), 'quantity' => 1, 'cost' => rand(200, 400)],
                ['name' => 'Air Filter', 'part_number' => 'AF-'.rand(1000, 9999), 'quantity' => 1, 'cost' => rand(150, 300)],
            ],
            [
                ['name' => 'Hydraulic Filter', 'part_number' => 'HF-'.rand(1000, 9999), 'quantity' => 2, 'cost' => rand(300, 600)],
                ['name' => 'Hydraulic Oil 20L', 'part_number' => 'HO-'.rand(1000, 9999), 'quantity' => 3, 'cost' => rand(800, 1200)],
            ],
            [
                ['name' => 'Brake Pads', 'part_number' => 'BP-'.rand(1000, 9999), 'quantity' => 4, 'cost' => rand(2000, 3500)],
            ],
            [
                ['name' => 'Tire 26.5R25', 'part_number' => 'TY-'.rand(1000, 9999), 'quantity' => 1, 'cost' => rand(15000, 25000)],
            ],
            [],
        ];

        return $partsSets[array_rand($partsSets)];
    }

    private function generatePolygonCoordinates(float $centerLat, float $centerLon, float $radius): array
    {
        $coordinates = [];
        $points = 6; // Hexagon

        for ($i = 0; $i < $points; $i++) {
            $angle = ($i / $points) * 2 * pi();
            $lat = $centerLat + ($radius * cos($angle));
            $lon = $centerLon + ($radius * sin($angle));
            $coordinates[] = ['lat' => $lat, 'lng' => $lon];
        }

        // Close the polygon
        $coordinates[] = $coordinates[0];

        return $coordinates;
    }

    private function printSummary(): void
    {
        $this->command->info('');
        $this->command->info('═══════════════════════════════════════════');
        $this->command->info('Multi-Team Data Seeding Summary');
        $this->command->info('═══════════════════════════════════════════');
        $this->command->info('Total Teams: '.count($this->allTeams));
        $this->command->info('Total Users: '.User::count());
        $this->command->info('');

        foreach ($this->allTeams as $teamData) {
            $team = $teamData['team'];
            $this->command->info("{$team->name}:");
            $this->command->info('   Users: '.$teamData['users']);
            $this->command->info('   Mine Areas: '.$teamData['areas']);
            $this->command->info('   Machines: '.$teamData['machines']);
            $this->command->info('   Geofences: '.Geofence::where('team_id', $team->id)->count());
            $this->command->info('   Routes: '.Route::where('team_id', $team->id)->count());
            $this->command->info('   Alerts: '.Alert::where('team_id', $team->id)->count());
            $this->command->info('   Maintenance Records: '.MaintenanceRecord::where('team_id', $team->id)->count());
            $this->command->info('');
        }

        $this->command->info('═══════════════════════════════════════════');
        $this->command->info('Total Database Records:');
        $this->command->info('  Machine Metrics: '.MachineMetric::count());
        $this->command->info('  Production Records: '.MineAreaProduction::count());
        $this->command->info('  Fuel Transactions: '.FuelTransaction::count());
        $this->command->info('  Activity Logs: '.ActivityLog::count());
        $this->command->info('═══════════════════════════════════════════');
    }
}
