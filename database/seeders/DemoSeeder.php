<?php

namespace Database\Seeders;

use App\Models\Alert;
use App\Models\Geofence;
use App\Models\Integration;
use App\Models\Machine;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create demo team
        $team = Team::create([
            'name' => 'Demo Mining Co.',
            'user_id' => 1, // Temporary, will be updated
            'personal_team' => false,
        ]);

        // Create roles and permissions for this team
        $this->call(RolePermissionSeeder::class);

        // Create demo users with different roles
        $users = [
            [
                'name' => 'Admin User',
                'email' => 'admin@demo.mines.local',
                'password' => bcrypt('password'),
                'role' => 'admin',
            ],
            [
                'name' => 'Fleet Manager',
                'email' => 'manager@demo.mines.local',
                'password' => bcrypt('password'),
                'role' => 'fleet_manager',
            ],
            [
                'name' => 'Operator',
                'email' => 'operator@demo.mines.local',
                'password' => bcrypt('password'),
                'role' => 'operator',
            ],
            [
                'name' => 'Viewer',
                'email' => 'viewer@demo.mines.local',
                'password' => bcrypt('password'),
                'role' => 'viewer',
            ],
        ];

        foreach ($users as $index => $userData) {
            $role = $userData['role'];
            unset($userData['role']);

            $user = User::create($userData);
            $user->teams()->attach($team->id);
            $user->update(['current_team_id' => $team->id]);

            // Assign role
            if ($index === 0) {
                $team->update(['user_id' => $user->id]);
            }

            $roleModel = $team->roles()->where('name', $role)->first();
            if ($roleModel) {
                $user->assignRole($roleModel);
            }
        }

        // Create demo machines
        $machines = [
            [
                'name' => 'Volvo A45G #001',
                'machine_type' => 'articulated_hauler',
                'registration_number' => 'VLV-A45G-2024-001',
                'serial_number' => 'VOLVO-SN-2024-001',
                'capacity' => 45000,
                'fuel_capacity' => 250,
                'status' => 'active',
                'last_location_latitude' => -25.8906,
                'last_location_longitude' => 28.2341,
            ],
            [
                'name' => 'CAT 390F #002',
                'machine_type' => 'excavator',
                'registration_number' => 'CAT-390F-2024-002',
                'serial_number' => 'CAT-SN-2024-002',
                'capacity' => 2500,
                'fuel_capacity' => 135,
                'status' => 'active',
                'last_location_latitude' => -25.8910,
                'last_location_longitude' => 28.2345,
            ],
            [
                'name' => 'Komatsu PC800 #003',
                'machine_type' => 'excavator',
                'registration_number' => 'KOMATSU-PC800-2024-003',
                'serial_number' => 'KOMATSU-SN-2024-003',
                'capacity' => 5200,
                'fuel_capacity' => 400,
                'status' => 'active',
                'last_location_latitude' => -25.8915,
                'last_location_longitude' => 28.2350,
            ],
            [
                'name' => 'Bell B40E #004',
                'machine_type' => 'articulated_hauler',
                'registration_number' => 'BELL-B40E-2024-004',
                'serial_number' => 'BELL-SN-2024-004',
                'capacity' => 40000,
                'fuel_capacity' => 220,
                'status' => 'maintenance',
                'last_location_latitude' => -25.8920,
                'last_location_longitude' => 28.2355,
            ],
            [
                'name' => 'LDV Truck #005',
                'machine_type' => 'support_vehicle',
                'registration_number' => 'LDV-TRUCK-2024-005',
                'serial_number' => 'LDV-SN-2024-005',
                'capacity' => 5000,
                'fuel_capacity' => 80,
                'status' => 'active',
                'last_location_latitude' => -25.8925,
                'last_location_longitude' => 28.2360,
            ],
        ];

        foreach ($machines as $machineData) {
            $machineData['team_id'] = $team->id;
            Machine::create($machineData);
        }

        // Create demo geofences
        $geofences = [
            [
                'name' => 'North Pit',
                'type' => 'pit',
                'center_latitude' => -25.8910,
                'center_longitude' => 28.2345,
                'coordinates' => json_encode([
                    ['lat' => -25.8900, 'lng' => 28.2330],
                    ['lat' => -25.8920, 'lng' => 28.2330],
                    ['lat' => -25.8920, 'lng' => 28.2360],
                    ['lat' => -25.8900, 'lng' => 28.2360],
                ]),
                'area_sqm' => 120000,
                'perimeter_m' => 1400,
                'description' => 'Main excavation pit - North sector',
            ],
            [
                'name' => 'South Stockpile',
                'type' => 'stockpile',
                'center_latitude' => -25.8950,
                'center_longitude' => 28.2355,
                'coordinates' => json_encode([
                    ['lat' => -25.8940, 'lng' => 28.2340],
                    ['lat' => -25.8960, 'lng' => 28.2340],
                    ['lat' => -25.8960, 'lng' => 28.2370],
                    ['lat' => -25.8940, 'lng' => 28.2370],
                ]),
                'area_sqm' => 90000,
                'perimeter_m' => 1200,
                'description' => 'Iron ore stockpile area',
            ],
            [
                'name' => 'Dump Site',
                'type' => 'dump',
                'center_latitude' => -25.8985,
                'center_longitude' => 28.2340,
                'coordinates' => json_encode([
                    ['lat' => -25.8970, 'lng' => 28.2320],
                    ['lat' => -25.9000, 'lng' => 28.2320],
                    ['lat' => -25.9000, 'lng' => 28.2360],
                    ['lat' => -25.8970, 'lng' => 28.2360],
                ]),
                'area_sqm' => 180000,
                'perimeter_m' => 1800,
                'description' => 'Waste material dump site',
            ],
            [
                'name' => 'Admin Facility',
                'type' => 'facility',
                'center_latitude' => -25.8860,
                'center_longitude' => 28.2410,
                'coordinates' => json_encode([
                    ['lat' => -25.8850, 'lng' => 28.2400],
                    ['lat' => -25.8870, 'lng' => 28.2400],
                    ['lat' => -25.8870, 'lng' => 28.2420],
                    ['lat' => -25.8850, 'lng' => 28.2420],
                ]),
                'area_sqm' => 40000,
                'perimeter_m' => 800,
                'description' => 'Administrative and maintenance facility',
            ],
        ];

        foreach ($geofences as $geofenceData) {
            $geofenceData['team_id'] = $team->id;
            Geofence::create($geofenceData);
        }

        // Create demo integrations
        $integrations = [
            [
                'name' => 'Volvo Integration',
                'provider' => 'volvo',
                'api_key' => 'demo_volvo_key_'.time(),
                'api_secret' => 'demo_volvo_secret_'.time(),
                'webhook_url' => env('APP_URL').'/webhooks/volvo',
                'webhook_secret' => 'demo_volvo_webhook_'.time(),
                'status' => 'active',
                'last_sync_at' => now()->subHours(2),
            ],
            [
                'name' => 'CAT Integration',
                'provider' => 'cat',
                'api_key' => 'demo_cat_key_'.time(),
                'api_secret' => 'demo_cat_secret_'.time(),
                'webhook_url' => env('APP_URL').'/webhooks/cat',
                'webhook_secret' => 'demo_cat_webhook_'.time(),
                'status' => 'active',
                'last_sync_at' => now()->subHours(1),
            ],
        ];

        foreach ($integrations as $integrationData) {
            $integrationData['team_id'] = $team->id;
            $integration = Integration::create($integrationData);
        }

        // Create demo alerts
        $alerts = [
            [
                'machine_id' => Machine::where('name', 'Volvo A45G #001')->first()->id,
                'type' => 'fuel',
                'priority' => 'high',
                'status' => 'active',
                'title' => 'Low Fuel Level',
                'description' => 'Fuel level below 20%',
                'triggered_at' => now()->subHours(3),
            ],
            [
                'machine_id' => Machine::where('name', 'CAT 390F #002')->first()->id,
                'type' => 'temperature',
                'priority' => 'critical',
                'status' => 'active',
                'title' => 'Engine Over Temperature',
                'description' => 'Engine temperature exceeding safe limits',
                'triggered_at' => now()->subHours(1),
            ],
            [
                'machine_id' => Machine::where('name', 'Komatsu PC800 #003')->first()->id,
                'type' => 'maintenance',
                'priority' => 'medium',
                'status' => 'acknowledged',
                'title' => 'Scheduled Maintenance Due',
                'description' => '500 operating hours reached',
                'triggered_at' => now()->subDays(1),
                'acknowledged_by' => User::where('email', 'manager@demo.mines.local')->first()->id,
                'acknowledged_at' => now()->subHours(12),
            ],
        ];

        foreach ($alerts as $alertData) {
            $alertData['team_id'] = $team->id;
            Alert::create($alertData);
        }

        $this->command->info('Demo data seeded successfully!');
        $this->command->info('Test users created:');
        $this->command->info('  - admin@demo.mines.local (Admin)');
        $this->command->info('  - manager@demo.mines.local (Fleet Manager)');
        $this->command->info('  - operator@demo.mines.local (Operator)');
        $this->command->info('  - viewer@demo.mines.local (Viewer)');
        $this->command->info('All passwords: password');
    }
}
