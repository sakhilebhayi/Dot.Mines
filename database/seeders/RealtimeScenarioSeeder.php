<?php

namespace Database\Seeders;

use App\Models\Alert;
use App\Models\FuelTransaction;
use App\Models\Geofence;
use App\Models\Machine;
use App\Models\MaintenanceRecord;
use App\Models\MineArea;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class RealtimeScenarioSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Starting real-time scenario seeder...');

        $team = Team::create([
            'name' => 'Roundebult Mining Operations',
            'user_id' => 1,
            'personal_team' => false,
        ]);

        $this->command->info('Created team: '.$team->name);

        $users = $this->createUsers($team);
        $this->command->info('Created '.count($users).' users');

        $mineAreas = $this->createMineAreas($team);
        $this->command->info('Created '.count($mineAreas).' mine areas');

        $machines = $this->createMachines($team, $mineAreas);
        $this->command->info('Created '.count($machines).' machines');

        $geofences = $this->createGeofences($team);
        $this->command->info('Created '.count($geofences).' geofences');

        $maintenanceRecords = $this->createMaintenanceRecords($team, $machines, $users);
        $this->command->info('Created '.count($maintenanceRecords).' maintenance records');

        $fuelTransactions = $this->createFuelTransactions($team, $machines, $users);
        $this->command->info('Created '.count($fuelTransactions).' fuel transactions');

        $healthStatuses = $this->createHealthStatuses($machines);
        $this->command->info('Created '.count($healthStatuses).' health status records');

        $geofenceEntries = $this->createGeofenceEntries($machines, $geofences);
        $this->command->info('Created '.count($geofenceEntries).' geofence entries');

        $alerts = $this->createAlerts($team, $machines);
        $this->command->info('Created '.count($alerts).' alerts');

        $this->command->info('');
        $this->command->info('Real-time scenario seed complete.');
        $this->command->info('');
        $this->command->info('Test Credentials:');
        $this->command->info('  Admin:     admin@roundebult.local / password');
        $this->command->info('  Manager:   manager@roundebult.local / password');
        $this->command->info('  Operator:  operator@roundebult.local / password');
        $this->command->info('  Mechanic:  mechanic@roundebult.local / password');
        $this->command->info('  Viewer:    viewer@roundebult.local / password');
    }

    /**
     * @return list<User>
     */
    private function createUsers(Team $team): array
    {
        $userData = [
            ['name' => 'James Kawasaki', 'email' => 'admin@roundebult.local', 'password' => bcrypt('password'), 'role' => 'admin'],
            ['name' => 'Sarah Thompson', 'email' => 'manager@roundebult.local', 'password' => bcrypt('password'), 'role' => 'fleet_manager'],
            ['name' => 'John Maseko', 'email' => 'operator@roundebult.local', 'password' => bcrypt('password'), 'role' => 'operator'],
            ['name' => 'David Nkosi', 'email' => 'mechanic@roundebult.local', 'password' => bcrypt('password'), 'role' => 'operator'],
            ['name' => 'Linda van der Merwe', 'email' => 'viewer@roundebult.local', 'password' => bcrypt('password'), 'role' => 'viewer'],
        ];

        $users = [];
        foreach ($userData as $index => $data) {
            $role = $data['role'];
            unset($data['role']);
            $user = User::create($data);
            $user->teams()->attach($team->id);
            $user->update(['current_team_id' => $team->id]);
            if ($index === 0) {
                $team->update(['user_id' => $user->id]);
            }
            $users[] = $user;
        }

        return $users;
    }

    /**
     * @return list<MineArea>
     */
    private function createMineAreas(Team $team): array
    {
        $areas = [
            [
                'name' => 'North Pit A', 'type' => 'pit', 'description' => 'Primary excavation pit',
                'center_latitude' => -26.1244, 'center_longitude' => 28.2360,
                'coordinates' => [[-26.1234, 28.2340], [-26.1254, 28.2340], [-26.1254, 28.2380], [-26.1234, 28.2380]],
                'area_sqm' => 185000, 'status' => 'active',
            ],
            [
                'name' => 'South Pit B', 'type' => 'pit', 'description' => 'Secondary excavation pit',
                'center_latitude' => -26.1300, 'center_longitude' => 28.2370,
                'coordinates' => [[-26.1290, 28.2350], [-26.1310, 28.2350], [-26.1310, 28.2390], [-26.1290, 28.2390]],
                'area_sqm' => 142000, 'status' => 'active',
            ],
            [
                'name' => 'Central Stockpile', 'type' => 'stockpile', 'description' => 'Main ore stockpile',
                'center_latitude' => -26.1268, 'center_longitude' => 28.2435,
                'coordinates' => [[-26.1260, 28.2420], [-26.1275, 28.2420], [-26.1275, 28.2450], [-26.1260, 28.2450]],
                'area_sqm' => 65000, 'status' => 'active',
            ],
            [
                'name' => 'Waste Dump Site', 'type' => 'dump', 'description' => 'Waste rock disposal',
                'center_latitude' => -26.1345, 'center_longitude' => 28.2345,
                'coordinates' => [[-26.1330, 28.2320], [-26.1360, 28.2320], [-26.1360, 28.2370], [-26.1330, 28.2370]],
                'area_sqm' => 220000, 'status' => 'active',
            ],
            [
                'name' => 'Processing Plant', 'type' => 'processing', 'description' => 'Ore processing facility',
                'center_latitude' => -26.1270, 'center_longitude' => 28.2470,
                'coordinates' => [[-26.1265, 28.2460], [-26.1275, 28.2460], [-26.1275, 28.2480], [-26.1265, 28.2480]],
                'area_sqm' => 35000, 'status' => 'active',
            ],
            [
                'name' => 'Maintenance Workshop', 'type' => 'facility', 'description' => 'Maintenance facility',
                'center_latitude' => -26.1205, 'center_longitude' => 28.2410,
                'coordinates' => [[-26.1200, 28.2400], [-26.1210, 28.2400], [-26.1210, 28.2420], [-26.1200, 28.2420]],
                'area_sqm' => 28000, 'status' => 'active',
            ],
        ];

        $mineAreas = [];
        foreach ($areas as $areaData) {
            $areaData['team_id'] = $team->id;
            $mineAreas[] = MineArea::create($areaData);
        }

        return $mineAreas;
    }

    /**
     * @param  list<MineArea>  $mineAreas
     * @return list<Machine>
     */
    private function createMachines(Team $team, array $mineAreas): array
    {
        $machineData = [
            ['name' => 'Komatsu PC800 - Alpha', 'machine_type' => 'excavator', 'registration_number' => 'KOM-PC800-001', 'serial_number' => 'PC800LC-8E0-50001', 'capacity' => 5200, 'fuel_capacity' => 400, 'status' => 'active', 'last_location_latitude' => -26.1244, 'last_location_longitude' => 28.2360, 'mine_area_id' => 1, 'hours_meter' => 2847],
            ['name' => 'CAT 390F - Bravo', 'machine_type' => 'excavator', 'registration_number' => 'CAT-390F-002', 'serial_number' => 'CAT0390FPMGG00102', 'capacity' => 4800, 'fuel_capacity' => 1350, 'status' => 'active', 'last_location_latitude' => -26.1300, 'last_location_longitude' => 28.2370, 'mine_area_id' => 2, 'hours_meter' => 3521],
            ['name' => 'Hitachi ZX870 - Charlie', 'machine_type' => 'excavator', 'registration_number' => 'HIT-ZX870-003', 'serial_number' => 'ZX870LC-6-100001', 'capacity' => 5000, 'fuel_capacity' => 1400, 'status' => 'maintenance', 'last_location_latitude' => -26.1205, 'last_location_longitude' => 28.2410, 'mine_area_id' => 6, 'hours_meter' => 4102],
            ['name' => 'Volvo A60H - Delta', 'machine_type' => 'articulated_hauler', 'registration_number' => 'VOL-A60H-004', 'serial_number' => 'VOLVOAH60H-201234', 'capacity' => 60000, 'fuel_capacity' => 620, 'status' => 'active', 'last_location_latitude' => -26.1248, 'last_location_longitude' => 28.2365, 'mine_area_id' => 1, 'hours_meter' => 2156],
            ['name' => 'Volvo A60H - Echo', 'machine_type' => 'articulated_hauler', 'registration_number' => 'VOL-A60H-005', 'serial_number' => 'VOLVOAH60H-201235', 'capacity' => 60000, 'fuel_capacity' => 620, 'status' => 'active', 'last_location_latitude' => -26.1252, 'last_location_longitude' => 28.2358, 'mine_area_id' => 1, 'hours_meter' => 1987],
            ['name' => 'Bell B50E - Foxtrot', 'machine_type' => 'articulated_hauler', 'registration_number' => 'BELL-B50E-006', 'serial_number' => 'BELLB50E-302567', 'capacity' => 50000, 'fuel_capacity' => 550, 'status' => 'active', 'last_location_latitude' => -26.1305, 'last_location_longitude' => 28.2375, 'mine_area_id' => 2, 'hours_meter' => 2643],
            ['name' => 'Bell B50E - Golf', 'machine_type' => 'articulated_hauler', 'registration_number' => 'BELL-B50E-007', 'serial_number' => 'BELLB50E-302568', 'capacity' => 50000, 'fuel_capacity' => 550, 'status' => 'active', 'last_location_latitude' => -26.1268, 'last_location_longitude' => 28.2435, 'mine_area_id' => 3, 'hours_meter' => 2891],
            ['name' => 'CAT 740 - Hotel', 'machine_type' => 'articulated_hauler', 'registration_number' => 'CAT-740-008', 'serial_number' => 'CAT740EJ-450123', 'capacity' => 42000, 'fuel_capacity' => 477, 'status' => 'active', 'last_location_latitude' => -26.1345, 'last_location_longitude' => 28.2345, 'mine_area_id' => 4, 'hours_meter' => 3276],
            ['name' => 'CAT D10T - India', 'machine_type' => 'dozer', 'registration_number' => 'CAT-D10T-009', 'serial_number' => 'CATD10T2-550234', 'capacity' => 35000, 'fuel_capacity' => 1000, 'status' => 'active', 'last_location_latitude' => -26.1340, 'last_location_longitude' => 28.2350, 'mine_area_id' => 4, 'hours_meter' => 1876],
            ['name' => 'Komatsu D375A - Juliet', 'machine_type' => 'dozer', 'registration_number' => 'KOM-D375-010', 'serial_number' => 'D375A-8-10234', 'capacity' => 32000, 'fuel_capacity' => 950, 'status' => 'active', 'last_location_latitude' => -26.1270, 'last_location_longitude' => 28.2440, 'mine_area_id' => 3, 'hours_meter' => 2234],
            ['name' => 'CAT 16M - Kilo', 'machine_type' => 'grader', 'registration_number' => 'CAT-16M-011', 'serial_number' => 'CAT16M3-650345', 'capacity' => 0, 'fuel_capacity' => 430, 'status' => 'active', 'last_location_latitude' => -26.1280, 'last_location_longitude' => 28.2385, 'mine_area_id' => null, 'hours_meter' => 1654],
            ['name' => 'Water Tanker - Lima', 'machine_type' => 'support_vehicle', 'registration_number' => 'WT-SUPP-012', 'serial_number' => 'WATERTANK-456789', 'capacity' => 30000, 'fuel_capacity' => 200, 'status' => 'active', 'last_location_latitude' => -26.1260, 'last_location_longitude' => 28.2370, 'mine_area_id' => null, 'hours_meter' => 987],
            ['name' => 'Fuel Bowser - Mike', 'machine_type' => 'support_vehicle', 'registration_number' => 'FB-SUPP-013', 'serial_number' => 'FUELBOWSER-789012', 'capacity' => 20000, 'fuel_capacity' => 150, 'status' => 'active', 'last_location_latitude' => -26.1242, 'last_location_longitude' => 28.2355, 'mine_area_id' => null, 'hours_meter' => 765],
            ['name' => 'Service Truck - November', 'machine_type' => 'support_vehicle', 'registration_number' => 'ST-SUPP-014', 'serial_number' => 'SERVICETRUCK-345678', 'capacity' => 5000, 'fuel_capacity' => 120, 'status' => 'active', 'last_location_latitude' => -26.1207, 'last_location_longitude' => 28.2412, 'mine_area_id' => 6, 'hours_meter' => 1432],
        ];

        $machines = [];
        foreach ($machineData as $data) {
            $data['team_id'] = $team->id;
            $data['mine_area_id'] = $mineAreas[$data['mine_area_id'] - 1]->id;
            $machines[] = Machine::create($data);
        }

        return $machines;
    }

    /**
     * @return list<Geofence>
     */
    private function createGeofences(Team $team): array
    {
        $geofenceData = [
            ['name' => 'North Pit A - Restricted', 'type' => 'restricted', 'center_latitude' => -26.1244, 'center_longitude' => 28.2360, 'coordinates' => json_encode([['lat' => -26.1234, 'lng' => 28.2340], ['lat' => -26.1254, 'lng' => 28.2340], ['lat' => -26.1254, 'lng' => 28.2380], ['lat' => -26.1234, 'lng' => 28.2380]]), 'description' => 'Active mining zone'],
            ['name' => 'Blast Zone', 'type' => 'danger', 'center_latitude' => -26.1240, 'center_longitude' => 28.2350, 'coordinates' => json_encode([['lat' => -26.1235, 'lng' => 28.2345], ['lat' => -26.1245, 'lng' => 28.2345], ['lat' => -26.1245, 'lng' => 28.2355], ['lat' => -26.1235, 'lng' => 28.2355]]), 'description' => 'Controlled blasting area'],
            ['name' => 'Dump Site Safety Zone', 'type' => 'restricted', 'center_latitude' => -26.1345, 'center_longitude' => 28.2345, 'coordinates' => json_encode([['lat' => -26.1330, 'lng' => 28.2320], ['lat' => -26.1360, 'lng' => 28.2320], ['lat' => -26.1360, 'lng' => 28.2370], ['lat' => -26.1330, 'lng' => 28.2370]]), 'description' => 'Waste dump perimeter'],
        ];

        $geofences = [];
        foreach ($geofenceData as $data) {
            $data['team_id'] = $team->id;
            $geofences[] = Geofence::create($data);
        }

        return $geofences;
    }

    /**
     * @param  list<Machine>  $machines
     * @param  list<User>  $users
     * @return list<MaintenanceRecord>
     */
    private function createMaintenanceRecords(Team $team, array $machines, array $users): array
    {
        $records = [];
        $records[] = MaintenanceRecord::create(['team_id' => $team->id, 'machine_id' => $machines[0]->id, 'maintenance_type' => 'routine', 'title' => '500 Hour Service', 'description' => 'Oil change, filter replacement', 'scheduled_date' => Carbon::now()->subDays(7), 'completed_at' => Carbon::now()->subDays(7), 'status' => 'completed', 'assigned_to' => $users[3]->id, 'total_cost' => 8500.00]);
        $records[] = MaintenanceRecord::create(['team_id' => $team->id, 'machine_id' => $machines[2]->id, 'maintenance_type' => 'corrective', 'title' => 'Hydraulic Pump Failure', 'description' => 'Main hydraulic pump seized', 'scheduled_date' => Carbon::now()->subDays(2), 'completed_at' => null, 'status' => 'in_progress', 'assigned_to' => $users[3]->id, 'total_cost' => 125000.00]);
        $records[] = MaintenanceRecord::create(['team_id' => $team->id, 'machine_id' => $machines[5]->id, 'maintenance_type' => 'preventive', 'title' => '1000 Hour Service', 'description' => 'Major service due', 'scheduled_date' => Carbon::now()->addDays(5), 'completed_at' => null, 'status' => 'scheduled', 'assigned_to' => null, 'total_cost' => 15000.00]);

        return $records;
    }

    /**
     * @param  list<Machine>  $machines
     * @param  list<User>  $users
     * @return list<FuelTransaction>
     */
    private function createFuelTransactions(Team $team, array $machines, array $users): array
    {
        $transactions = [];
        $startDate = Carbon::now()->subDays(30);
        foreach ($machines as $machine) {
            if ($machine->fuel_capacity == 0) {
                continue;
            }
            $refuelCount = rand(5, 10);
            for ($i = 0; $i < $refuelCount; $i++) {
                $date = $startDate->copy()->addDays(rand(0, 30))->addHours(rand(6, 20));
                $quantity = rand(60, 95) / 100 * $machine->fuel_capacity;
                $pricePerLiter = rand(2100, 2350) / 100;
                $transactions[] = FuelTransaction::create(['team_id' => $team->id, 'machine_id' => $machine->id, 'user_id' => $users[rand(2, 3)]->id, 'transaction_type' => 'dispensing', 'quantity_liters' => round($quantity, 2), 'unit_price' => $pricePerLiter, 'total_cost' => round($quantity * $pricePerLiter, 2), 'fuel_type' => 'diesel', 'transaction_date' => $date, 'odometer_reading' => $machine->hours_meter + rand(-100, 50)]);
            }
        }

        return $transactions;
    }

    /**
     * @param  list<Machine>  $machines
     * @return array{}
     */
    private function createHealthStatuses(array $machines): array
    {
        // Skip health statuses for now - table structure varies
        return [];
    }

    /**
     * @param  list<Geofence>  $geofences
     * @param  list<Machine>  $machines
     * @return array{}
     */
    private function createGeofenceEntries(array $machines, array $geofences): array
    {
        // Skip geofence entries due to complex schema requirements
        return [];
    }

    /**
     * @param  list<Machine>  $machines
     * @return list<Alert>
     */
    private function createAlerts(Team $team, array $machines): array
    {
        $alerts = [];
        $alerts[] = Alert::create(['team_id' => $team->id, 'machine_id' => $machines[4]->id, 'type' => 'fuel', 'priority' => 'high', 'status' => 'active', 'title' => 'Low Fuel Level', 'description' => 'Fuel level at 18%', 'triggered_at' => Carbon::now()->subHours(2)]);
        $alerts[] = Alert::create(['team_id' => $team->id, 'machine_id' => $machines[5]->id, 'type' => 'maintenance', 'priority' => 'medium', 'status' => 'active', 'title' => 'Scheduled Maintenance Due', 'description' => '1000 hour service due in 5 days', 'triggered_at' => Carbon::now()->subDays(1)]);
        $alerts[] = Alert::create(['team_id' => $team->id, 'machine_id' => $machines[2]->id, 'type' => 'breakdown', 'priority' => 'critical', 'status' => 'active', 'title' => 'Machine Breakdown', 'description' => 'Hydraulic pump failure', 'triggered_at' => Carbon::now()->subDays(2)]);

        return $alerts;
    }
}
