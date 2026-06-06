<?php

namespace Database\Seeders;

use App\Models\AIAgent;
use App\Models\AIAnalysisSession;
use App\Models\AIInsight;
use App\Models\AIPredictiveAlert;
use App\Models\BellEquipment;
use App\Models\BellEquipmentCautionCode;
use App\Models\BellEquipmentCurrentStatus;
use App\Models\BellEquipmentDailyKpi;
use App\Models\BellEquipmentFuelUsageHistory;
use App\Models\BellEquipmentHealthHistory;
use App\Models\BellEquipmentIdleHoursHistory;
use App\Models\BellEquipmentLoadCountHistory;
use App\Models\BellEquipmentLocationHistory;
use App\Models\BellEquipmentOperatingHoursHistory;
use App\Models\BellEquipmentTelemetryHistory;
use App\Models\BellFleetSnapshot;
use App\Models\ComplianceReport;
use App\Models\ComplianceViolation;
use App\Models\FuelBudget;
use App\Models\HealthMetric;
use App\Models\Integration;
use App\Models\IoTSensor;
use App\Models\Machine;
use App\Models\MachineHealthStatus;
use App\Models\MineArea;
use App\Models\ProductionTarget;
use App\Models\SensorReading;
use App\Models\ShiftTemplate;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * RealisticPlatformSeeder
 *
 * Seeds all platform pages that are not covered by ComprehensiveDataSeeder or DemoDataSeeder:
 *   - Subscription Plans + active team subscription
 *   - Bell Equipment integration (fleet of 6 machines with full telemetry)
 *   - Integrations record for Bell
 *   - Machine Health Status for every machine
 *   - Health Metrics (sensor readings per machine)
 *   - IoT Sensors + Sensor Readings
 *   - Compliance Violations + Compliance Reports
 *   - Fuel Budgets (monthly + quarterly)
 *   - Production Targets (monthly/quarterly per mine area)
 *   - Shift Templates
 *   - AI Insights + Predictive Alerts + Analysis Sessions
 *   - Bell OEM Intelligence history data
 */
class RealisticPlatformSeeder extends Seeder
{
    private Team $team;

    /** @var Collection<int, Machine> */
    private mixed $machines;

    /** @var Collection<int, MineArea> */
    private mixed $areas;

    /** @var Collection<int, User> */
    private mixed $users;

    public function run(): void
    {
        $this->command->info('🔧 Starting RealisticPlatformSeeder...');

        $this->team = Team::first();

        if (! $this->team) {
            $this->command->error('No team found. Run ComprehensiveDataSeeder first.');

            return;
        }

        $this->machines = Machine::where('team_id', $this->team->id)->get();
        $this->areas = MineArea::where('team_id', $this->team->id)->get();
        $this->users = $this->team->users()->get();

        $this->seedSubscriptionPlans();
        $this->seedTeamSubscription();
        $this->seedBellEquipment();
        $this->seedIntegrations();
        $this->seedMachineHealthStatus();
        $this->seedHealthMetrics();
        $this->seedIotSensors();
        $this->seedComplianceData();
        $this->seedFuelBudgets();
        $this->seedProductionTargets();
        $this->seedShiftTemplates();
        $this->seedAiInsights();
        $this->seedAiPredictiveAlerts();
        $this->seedAiAnalysisSessions();

        $this->command->info('✅ RealisticPlatformSeeder completed.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Subscriptions
    // ──────────────────────────────────────────────────────────────────────────

    private function seedSubscriptionPlans(): void
    {
        if (SubscriptionPlan::exists()) {
            $this->command->info('  ↳ Subscription plans already seeded.');

            return;
        }

        $this->call(SubscriptionPlanSeeder::class);
        $this->command->info('  ✓ Subscription plans seeded.');
    }

    private function seedTeamSubscription(): void
    {
        if (Subscription::where('team_id', $this->team->id)->exists()) {
            $this->command->info('  ↳ Team subscription already seeded.');

            return;
        }

        $enterprise = SubscriptionPlan::where('slug', 'enterprise')->first();

        if (! $enterprise) {
            $this->command->warn('  ↳ No enterprise plan found, skipping subscription.');

            return;
        }

        Subscription::create([
            'team_id' => $this->team->id,
            'subscription_plan_id' => $enterprise->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'trial_ends_at' => null,
            'current_period_start' => now()->startOfMonth(),
            'current_period_end' => now()->endOfMonth(),
            'canceled_at' => null,
            'ends_at' => null,
            'paystack_subscription_code' => 'SUB_demo_enterprise_001',
            'paystack_customer_code' => 'CUS_demo_admin_001',
            'paystack_email_token' => null,
            'metadata' => ['demo' => true],
        ]);

        $this->command->info('  ✓ Team subscription (Enterprise) seeded.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Bell Equipment (6 machines)
    // ──────────────────────────────────────────────────────────────────────────

    private function seedBellEquipment(): void
    {
        if (BellEquipment::exists()) {
            $this->command->info('  ↳ Bell equipment already seeded.');

            return;
        }

        // Base coordinates for Mpumalanga coal mine area
        $baseLat = -25.8906;
        $baseLon = 28.2341;

        $fleet = [
            ['id' => 'BELL-B50E-2021-001', 'serial' => 'BLLB50E20210001', 'model' => 'B50E',  'type' => 'ADT', 'lat_off' => 0.001,  'lon_off' => 0.001,  'op_hours' => 4821.5,  'fuel' => 44200, 'loads' => 18430, 'def' => 74.3],
            ['id' => 'BELL-B50E-2021-002', 'serial' => 'BLLB50E20210002', 'model' => 'B50E',  'type' => 'ADT', 'lat_off' => -0.002, 'lon_off' => 0.002,  'op_hours' => 4612.0,  'fuel' => 43800, 'loads' => 17990, 'def' => 81.0],
            ['id' => 'BELL-B40E-2022-003', 'serial' => 'BLLB40E20220003', 'model' => 'B40E',  'type' => 'ADT', 'lat_off' => 0.003,  'lon_off' => -0.001, 'op_hours' => 3105.2,  'fuel' => 36100, 'loads' => 14220, 'def' => 62.5],
            ['id' => 'BELL-B40E-2022-004', 'serial' => 'BLLB40E20220004', 'model' => 'B40E',  'type' => 'ADT', 'lat_off' => -0.001, 'lon_off' => -0.002, 'op_hours' => 2987.8,  'fuel' => 35400, 'loads' => 13810, 'def' => 88.2],
            ['id' => 'BELL-B50E-2020-005', 'serial' => 'BLLB50E20200005', 'model' => 'B50E',  'type' => 'ADT', 'lat_off' => 0.0005, 'lon_off' => 0.003,  'op_hours' => 6340.1,  'fuel' => 58900, 'loads' => 24100, 'def' => 45.8],
            ['id' => 'BELL-B60E-2023-006', 'serial' => 'BLLB60E20230006', 'model' => 'B60E',  'type' => 'ADT', 'lat_off' => -0.003, 'lon_off' => 0.0005, 'op_hours' => 1204.5,  'fuel' => 12400, 'loads' => 4820,  'def' => 91.0],
        ];

        $now = now();

        foreach ($fleet as $idx => $f) {
            $equipment = BellEquipment::create([
                'oem_name' => 'Bell Equipment',
                'model' => $f['model'],
                'equipment_id' => $f['id'],
                'serial_number' => $f['serial'],
                'pin' => strtoupper(substr(md5($f['serial']), 0, 17)),
                'unit_install_date_time' => Carbon::createFromDate(2020 + $idx % 4, rand(1, 12), rand(1, 28)),
            ]);

            $key = $equipment->equipment_key;
            $lat = round($baseLat + $f['lat_off'], 6);
            $lon = round($baseLon + $f['lon_off'], 6);

            // Current status
            BellEquipmentCurrentStatus::insert([
                'equipment_key' => $key,
                'snapshot_time' => $now->copy()->subMinutes(rand(1, 25))->format('Y-m-d H:i:s'),
                'latitude' => $lat,
                'longitude' => $lon,
                'idle_hours' => round($f['op_hours'] * 0.18, 1),
                'load_count' => $f['loads'],
                'operating_hours' => $f['op_hours'],
                'payload' => round(rand(38000, 52000) / 1000, 2),
                'payload_units' => 'tonne',
                'def_percent' => $f['def'],
                'odometer' => round($f['op_hours'] * 18.3, 1),
                'odometer_units' => 'km',
                'fuel_consumed' => $f['fuel'],
                'fuel_units' => 'litre',
                'fuel_remaining_percent' => round(rand(2200, 4800) / 55 * 100 / 1000, 1),
                'engine_running' => $idx < 5 ? 1 : 0,
                'engine_number' => 'ENG-'.strtoupper(substr($f['serial'], -6)),
                'last_telemetry_date' => $now->copy()->subMinutes(rand(1, 20))->format('Y-m-d H:i:s'),
                'updated_date' => $now->copy()->subMinutes(rand(1, 5))->format('Y-m-d H:i:s'),
            ]);

            // Telemetry history — 30 days, one snapshot per 4 hours
            $this->seedBellTelemetryHistory($key, $f, $baseLat, $baseLon);

            // Daily KPIs — 30 days
            $this->seedBellDailyKpis($key, $f);

            // OEM Intelligence history
            $this->seedBellOemHistory($key, $f, $lat, $lon);
        }

        // Fleet snapshot
        BellFleetSnapshot::insert([
            'snapshot_time' => $now->copy()->subMinutes(15)->format('Y-m-d H:i:s'),
            'fleet_version' => 'ISO15143-3/2.1.0',
            'equipment_count' => 6,
            'raw_json' => json_encode(['snapshot' => 'demo', 'count' => 6]),
            'created_date' => $now->copy()->subMinutes(15)->format('Y-m-d H:i:s'),
        ]);

        $this->command->info('  ✓ Bell equipment (6 machines) + telemetry seeded.');
    }

    /** @param array<string, mixed> $f */
    private function seedBellTelemetryHistory(int $key, array $f, float $baseLat, float $baseLon): void
    {
        $rows = [];
        for ($h = 720; $h >= 0; $h -= 4) {
            $snap = now()->subHours($h);
            $hourFraction = $f['op_hours'] - ($h / 24 * 8);
            $fuelConsumed = $f['fuel'] - ($h * 18);
            if ($fuelConsumed < 0) {
                $fuelConsumed = 0;
            }

            $rows[] = [
                'equipment_key' => $key,
                'snapshot_time' => $snap->format('Y-m-d H:i:s'),
                'latitude' => round($baseLat + $f['lat_off'] + (rand(-50, 50) / 10000), 6),
                'longitude' => round($baseLon + $f['lon_off'] + (rand(-50, 50) / 10000), 6),
                'idle_hours' => round($hourFraction * 0.18, 2),
                'load_count' => max(0, (int) ($f['loads'] - ($h / 4 * 4))),
                'operating_hours' => round(max(0, $hourFraction), 1),
                'fuel_consumed' => round($fuelConsumed, 1),
                'fuel_units' => 'litre',
                'fuel_remaining_percent' => round(60 - ($h % 200) / 10, 1),
                'def_percent' => round(max(10, $f['def'] - ($h % 100) / 5), 1),
                'engine_running' => ($h % 12 < 8) ? 1 : 0,
                'engine_number' => 'ENG-'.strtoupper(substr($f['serial'], -6)),
                'payload' => round(rand(36, 52), 1),
                'payload_units' => 'tonne',
                'odometer' => round($hourFraction * 18.3, 1),
                'odometer_units' => 'km',
                'telemetry_date' => $snap->format('Y-m-d H:i:s'),
                'created_date' => $snap->format('Y-m-d H:i:s'),
            ];
        }

        foreach (array_chunk($rows, 50) as $chunk) {
            BellEquipmentTelemetryHistory::insert($chunk);
        }
    }

    /** @param array<string, mixed> $f */
    private function seedBellDailyKpis(int $key, array $f): void
    {
        $rows = [];
        for ($d = 30; $d >= 0; $d--) {
            $date = now()->subDays($d)->toDateString();
            $loads = rand(55, 95);
            $tons = $loads * rand(38, 52);
            $opHours = round(rand(80, 120) / 12, 1);
            $idleHours = round(rand(8, 25) / 10, 1);
            $utilization = $opHours > 0 ? round($opHours / ($opHours + $idleHours + 0.5) * 100, 1) : 0;

            $rows[] = [
                'equipment_key' => $key,
                'kpi_date' => $date,
                'operating_hours' => $opHours,
                'idle_hours' => $idleHours,
                'loads_moved' => $loads,
                'payload_moved' => $tons,
                'distance_travelled' => round($loads * rand(35, 65) / 10, 1),
                'fuel_used' => round($loads * rand(14, 18), 1),
                'utilization_percent' => $utilization,
                'created_date' => now()->subDays($d)->format('Y-m-d H:i:s'),
            ];
        }

        foreach (array_chunk($rows, 31) as $chunk) {
            BellEquipmentDailyKpi::insert($chunk);
        }
    }

    /** @param array<string, mixed> $f */
    private function seedBellOemHistory(int $key, array $f, float $lat, float $lon): void
    {
        $source = 'seeder';

        // Location history — 48 hourly readings
        $locRows = [];
        for ($h = 47; $h >= 0; $h--) {
            $locRows[] = [
                'equipment_key' => $key,
                'latitude' => round($lat + (rand(-80, 80) / 10000), 6),
                'longitude' => round($lon + (rand(-80, 80) / 10000), 6),
                'heading_degrees' => rand(0, 359),
                'speed_kmh' => round(rand(0, 420) / 10, 1),
                'source' => $source,
                'recorded_at' => now()->subHours($h)->format('Y-m-d H:i:s'),
                'created_at' => now()->subHours($h)->format('Y-m-d H:i:s'),
            ];
        }
        BellEquipmentLocationHistory::insert($locRows);

        // Operating hours history — daily
        $opRows = [];
        for ($d = 30; $d >= 0; $d--) {
            $opRows[] = [
                'equipment_key' => $key,
                'operating_hours' => round($f['op_hours'] - ($d * 7.2), 1),
                'source' => $source,
                'recorded_at' => now()->subDays($d)->format('Y-m-d H:i:s'),
                'created_at' => now()->subDays($d)->format('Y-m-d H:i:s'),
            ];
        }
        BellEquipmentOperatingHoursHistory::insert($opRows);

        // Fuel usage history — daily
        $fuelRows = [];
        for ($d = 30; $d >= 0; $d--) {
            $fuelRows[] = [
                'equipment_key' => $key,
                'fuel_used_cumulative' => round($f['fuel'] - ($d * 430), 1),
                'fuel_remaining_percent' => round(40 + ($d % 60), 1),
                'fuel_units' => 'litre',
                'source' => $source,
                'recorded_at' => now()->subDays($d)->format('Y-m-d H:i:s'),
                'created_at' => now()->subDays($d)->format('Y-m-d H:i:s'),
            ];
        }
        BellEquipmentFuelUsageHistory::insert($fuelRows);

        // Idle hours history — daily
        $idleRows = [];
        for ($d = 30; $d >= 0; $d--) {
            $idleRows[] = [
                'equipment_key' => $key,
                'idle_hours' => round($f['op_hours'] * 0.18 - ($d * 1.3), 1),
                'source' => $source,
                'recorded_at' => now()->subDays($d)->format('Y-m-d H:i:s'),
                'created_at' => now()->subDays($d)->format('Y-m-d H:i:s'),
            ];
        }
        BellEquipmentIdleHoursHistory::insert($idleRows);

        // Load count history — daily
        $loadRows = [];
        for ($d = 30; $d >= 0; $d--) {
            $loadRows[] = [
                'equipment_key' => $key,
                'load_count' => max(0, $f['loads'] - ($d * 62)),
                'cumulative_payload' => round(max(0, $f['loads'] - ($d * 62)) * 46.2, 1),
                'payload_units' => 'tonne',
                'source' => $source,
                'recorded_at' => now()->subDays($d)->format('Y-m-d H:i:s'),
                'created_at' => now()->subDays($d)->format('Y-m-d H:i:s'),
            ];
        }
        BellEquipmentLoadCountHistory::insert($loadRows);

        // Health history — daily
        $healthRows = [];
        for ($d = 30; $d >= 0; $d--) {
            $defPct = round(max(10, $f['def'] - ($d * 2) + rand(-5, 5)), 1);
            $healthRows[] = [
                'equipment_key' => $key,
                'engine_condition' => $defPct > 50 ? 'Normal' : 'Warning',
                'def_remaining_percent' => $defPct,
                'active_regen_hours' => round(rand(0, 30) / 10, 1),
                'caution_code_count' => rand(0, 3),
                'health_score' => round(80 + rand(-10, 15), 1),
                'recorded_at' => now()->subDays($d)->format('Y-m-d H:i:s'),
                'created_at' => now()->subDays($d)->format('Y-m-d H:i:s'),
            ];
        }
        BellEquipmentHealthHistory::insert($healthRows);

        // Caution codes — a few active ones
        $cautionCodes = [
            ['code' => 'SPN524/FMI2', 'desc' => 'Engine Coolant Temperature – Warning', 'severity' => 'Warning'],
            ['code' => 'SPN110/FMI0', 'desc' => 'DEF Level Low', 'severity' => 'Warning'],
        ];
        foreach (array_slice($cautionCodes, 0, rand(0, 2)) as $cc) {
            BellEquipmentCautionCode::create([
                'equipment_key' => $key,
                'fault_code' => $cc['code'],
                'fault_description' => $cc['desc'],
                'severity' => $cc['severity'],
                'source' => $source,
                'is_active' => true,
                'occurred_at' => now()->subHours(rand(1, 72)),
                'cleared_at' => null,
            ]);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Integrations
    // ──────────────────────────────────────────────────────────────────────────

    private function seedIntegrations(): void
    {
        if (Integration::where('team_id', $this->team->id)->exists()) {
            $this->command->info('  ↳ Integrations already seeded.');

            return;
        }

        $integrations = [
            [
                'provider' => 'bell',
                'name' => 'Bell Equipment Fleet — ISO 15143-3',
                'api_key' => 'ISO_Export_Service',
                'api_secret' => '',
                'status' => 'active',
                'machines_count' => 6,
                'last_sync_at' => now()->subMinutes(15),
                'last_sync_status' => 'success',
                'last_error' => null,
                'config' => json_encode([
                    'base_url' => 'https://b-fleet03.bellequipment.com:8080',
                    'sync_interval' => '15min',
                    'endpoints' => ['Fleet', 'Equipment'],
                    'sso_enabled' => true,
                ]),
                'credentials' => json_encode([
                    'sso_token_url' => 'https://sso.bellequipment.com/connect/token',
                    'client_id' => 'ISO_Export_Service',
                    'scope' => 'ISO_Exports',
                ]),
            ],
            [
                'provider' => 'samsara',
                'name' => 'Samsara GPS — Auxiliary Fleet',
                'api_key' => '',
                'api_secret' => '',
                'status' => 'inactive',
                'machines_count' => 0,
                'last_sync_at' => null,
                'last_sync_status' => null,
                'last_error' => 'API key not configured.',
                'config' => json_encode(['org_id' => '']),
                'credentials' => null,
            ],
            [
                'provider' => 'fleetio',
                'name' => 'Fleetio Maintenance Connector',
                'api_key' => '',
                'api_secret' => '',
                'status' => 'inactive',
                'machines_count' => 0,
                'last_sync_at' => null,
                'last_sync_status' => null,
                'last_error' => null,
                'config' => json_encode([]),
                'credentials' => null,
            ],
        ];

        foreach ($integrations as $int) {
            Integration::create(array_merge($int, ['team_id' => $this->team->id]));
        }

        $this->command->info('  ✓ Integrations seeded (Bell active, 2 inactive).');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Machine Health Status
    // ──────────────────────────────────────────────────────────────────────────

    private function seedMachineHealthStatus(): void
    {
        if (MachineHealthStatus::where('team_id', $this->team->id)->exists()) {
            $this->command->info('  ↳ Machine health status already seeded.');

            return;
        }

        foreach ($this->machines as $machine) {
            $engine = rand(72, 98);
            $trans = rand(70, 97);
            $hydraulics = rand(68, 99);
            $electrical = rand(75, 99);
            $brakes = rand(80, 99);
            $cooling = rand(70, 98);
            $overall = (int) round(($engine + $trans + $hydraulics + $electrical + $brakes + $cooling) / 6);

            $faultCodes = $overall < 80 ? [
                ['code' => 'SPN110/FMI0', 'description' => 'Engine temperature marginal', 'severity' => 'medium'],
            ] : [];

            MachineHealthStatus::create([
                'team_id' => $this->team->id,
                'machine_id' => $machine->id,
                'overall_health_score' => $overall,
                'health_status' => match (true) {
                    $overall >= 90 => 'excellent',
                    $overall >= 75 => 'good',
                    $overall >= 60 => 'fair',
                    default => 'poor',
                },
                'component_scores' => json_encode([
                    'engine' => $engine, 'transmission' => $trans,
                    'hydraulics' => $hydraulics, 'electrical' => $electrical,
                    'brakes' => $brakes, 'cooling' => $cooling,
                ]),
                'engine_health' => $engine,
                'transmission_health' => $trans,
                'hydraulics_health' => $hydraulics,
                'electrical_health' => $electrical,
                'brakes_health' => $brakes,
                'cooling_system_health' => $cooling,
                'last_diagnostic_scan' => now()->subHours(rand(1, 48)),
                'active_fault_codes' => json_encode($faultCodes),
                'fault_code_count' => count($faultCodes),
                'recommendations' => json_encode(
                    $overall < 80
                        ? ['Schedule hydraulic service within 100 hours', 'Monitor coolant temperature closely']
                        : ['All systems within normal parameters']
                ),
            ]);
        }

        $this->command->info('  ✓ Machine health status seeded for '.count($this->machines).' machines.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Health Metrics (detailed sensor readings per machine)
    // ──────────────────────────────────────────────────────────────────────────

    private function seedHealthMetrics(): void
    {
        if (HealthMetric::where('team_id', $this->team->id)->exists()) {
            $this->command->info('  ↳ Health metrics already seeded.');

            return;
        }

        $metricTypes = [
            ['type' => 'temperature',    'component' => 'engine_coolant',  'unit' => '°C',   'min' => 75,  'max' => 105, 'gen' => [80, 100]],
            ['type' => 'temperature',    'component' => 'hydraulic_oil',   'unit' => '°C',   'min' => 40,  'max' => 80,  'gen' => [45, 75]],
            ['type' => 'pressure',       'component' => 'engine_oil',      'unit' => 'kPa',  'min' => 250, 'max' => 450, 'gen' => [280, 420]],
            ['type' => 'pressure',       'component' => 'hydraulic_pump',  'unit' => 'bar',  'min' => 180, 'max' => 350, 'gen' => [200, 330]],
            ['type' => 'vibration',      'component' => 'drivetrain',      'unit' => 'mm/s', 'min' => 0,   'max' => 7,   'gen' => [1, 6]],
            ['type' => 'fuel_level',     'component' => 'main_tank',       'unit' => '%',    'min' => 10,  'max' => 100, 'gen' => [20, 95]],
            ['type' => 'voltage',        'component' => 'battery',         'unit' => 'V',    'min' => 23,  'max' => 29,  'gen' => [24, 28]],
            ['type' => 'hours',          'component' => 'air_filter',      'unit' => 'h',    'min' => 0,   'max' => 500, 'gen' => [50, 450]],
        ];

        $rows = [];
        foreach ($this->machines->take(12) as $machine) {
            foreach ($metricTypes as $mt) {
                // 24 readings, one per hour for the last day
                for ($h = 23; $h >= 0; $h--) {
                    $value = round(rand($mt['gen'][0] * 10, $mt['gen'][1] * 10) / 10, 1);
                    $isNormal = $value >= $mt['min'] && $value <= $mt['max'];

                    $rows[] = [
                        'team_id' => $this->team->id,
                        'machine_id' => $machine->id,
                        'recorded_at' => now()->subHours($h)->format('Y-m-d H:i:s'),
                        'metric_type' => $mt['type'],
                        'component' => $mt['component'],
                        'value' => $value,
                        'unit' => $mt['unit'],
                        'normal_min' => $mt['min'],
                        'normal_max' => $mt['max'],
                        'is_normal' => $isNormal ? 1 : 0,
                        'severity' => $isNormal ? 'normal' : ($value > $mt['max'] * 1.1 ? 'critical' : 'warning'),
                        'notes' => null,
                        'created_at' => now()->subHours($h)->format('Y-m-d H:i:s'),
                        'updated_at' => now()->subHours($h)->format('Y-m-d H:i:s'),
                    ];
                }
            }
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            HealthMetric::insert($chunk);
        }

        $this->command->info('  ✓ Health metrics seeded ('.count($rows).' readings).');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // IoT Sensors + Sensor Readings
    // ──────────────────────────────────────────────────────────────────────────

    private function seedIotSensors(): void
    {
        if (IoTSensor::where('team_id', $this->team->id)->exists()) {
            $this->command->info('  ↳ IoT sensors already seeded.');

            return;
        }

        $baseLat = -25.8906;
        $baseLon = 28.2341;

        $sensorDefs = [
            ['name' => 'North Pit Air Quality Monitor',   'type' => 'air_quality',   'lat' => $baseLat + 0.002,  'lon' => $baseLon + 0.001],
            ['name' => 'South Pit Dust Sensor',           'type' => 'dust',          'lat' => $baseLat - 0.002, 'lon' => $baseLon - 0.001],
            ['name' => 'Processing Plant Noise Meter',    'type' => 'noise',         'lat' => $baseLat + 0.001,  'lon' => $baseLon + 0.003],
            ['name' => 'Main Stockpile Ground Vibration', 'type' => 'vibration',     'lat' => $baseLat - 0.001, 'lon' => $baseLon + 0.002],
            ['name' => 'Fuel Bay Pressure Sensor',        'type' => 'pressure',      'lat' => $baseLat + 0.0005, 'lon' => $baseLon - 0.002],
            ['name' => 'Workshop Ambient Temperature',    'type' => 'temperature',   'lat' => $baseLat - 0.003, 'lon' => $baseLon + 0.0005],
            ['name' => 'East Pit Humidity Sensor',        'type' => 'humidity',      'lat' => $baseLat + 0.003,  'lon' => $baseLon - 0.003],
            ['name' => 'Crusher Acceleration Monitor',   'type' => 'vibration',     'lat' => $baseLat - 0.004, 'lon' => $baseLon + 0.004],
        ];

        $unitMap = [
            'air_quality' => 'AQI', 'dust' => 'mg/m³', 'noise' => 'dB',
            'vibration' => 'mm/s', 'pressure' => 'bar', 'temperature' => '°C',
            'humidity' => '%RH',
        ];

        $readingRanges = [
            'air_quality' => [30, 150], 'dust' => [5, 80], 'noise' => [60, 110],
            'vibration' => [5, 50], 'pressure' => [10, 70], 'temperature' => [180, 420],
            'humidity' => [200, 850],
        ];

        foreach ($sensorDefs as $idx => $def) {
            $area = $this->areas->isNotEmpty() ? $this->areas->get($idx % $this->areas->count()) : null;
            $range = $readingRanges[$def['type']] ?? [0, 100];
            $lastValue = round(rand((int) ($range[0] * 10), (int) ($range[1] * 10)) / 10, 1);

            $sensor = IoTSensor::create([
                'team_id' => $this->team->id,
                'mine_area_id' => $area?->id,
                'name' => $def['name'],
                'sensor_type' => $def['type'],
                'device_id' => 'IOT-'.str_pad((string) ($idx + 1), 4, '0', STR_PAD_LEFT),
                'status' => $idx < 7 ? 'active' : 'maintenance',
                'last_reading' => json_encode(['value' => $lastValue, 'unit' => $unitMap[$def['type']] ?? 'unit']),
                'last_reading_at' => now()->subMinutes(rand(1, 30)),
                'location_latitude' => round($def['lat'], 6),
                'location_longitude' => round($def['lon'], 6),
                'metadata' => json_encode(['firmware' => '2.4.1', 'battery_pct' => rand(60, 100)]),
            ]);

            // Seed 48 hours of readings
            $readingRows = [];
            for ($h = 47; $h >= 0; $h--) {
                $readingRows[] = [
                    'iot_sensor_id' => $sensor->id,
                    'sensor_type' => $def['type'],
                    'value' => round(rand((int) ($range[0] * 10), (int) ($range[1] * 10)) / 10, 1),
                    'unit' => $unitMap[$def['type']] ?? 'unit',
                    'timestamp' => now()->subHours($h)->format('Y-m-d H:i:s'),
                    'quality_score' => round(rand(85, 100) / 100, 2),
                    'created_at' => now()->subHours($h)->format('Y-m-d H:i:s'),
                    'updated_at' => now()->subHours($h)->format('Y-m-d H:i:s'),
                ];
            }
            SensorReading::insert($readingRows);
        }

        $this->command->info('  ✓ IoT sensors (8) + sensor readings seeded.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Compliance Violations + Reports
    // ──────────────────────────────────────────────────────────────────────────

    private function seedComplianceData(): void
    {
        if (ComplianceViolation::where('team_id', $this->team->id)->exists()) {
            $this->command->info('  ↳ Compliance data already seeded.');

            return;
        }

        $admin = $this->users->first();

        $violations = [
            ['type' => 'speed_violation',    'severity' => 'medium',   'desc' => 'Haul truck TRK-112 exceeded 40 km/h speed limit on Haul Road 3B by 18 km/h.',                         'days_ago' => 2,  'resolved' => false],
            ['type' => 'exclusion_zone',      'severity' => 'high',     'desc' => 'Light vehicle LV-004 entered blast exclusion zone during active blasting preparation at North Pit.',    'days_ago' => 5,  'resolved' => true],
            ['type' => 'pre_shift_inspection', 'severity' => 'medium',  'desc' => 'EXC-003 dispatched without completing mandatory pre-shift inspection checklist.',                       'days_ago' => 7,  'resolved' => true],
            ['type' => 'fatigue',             'severity' => 'high',     'desc' => 'Operator J. Dlamini exceeded 12-hour consecutive shift limit (13h 20min recorded).',                   'days_ago' => 9,  'resolved' => true],
            ['type' => 'environmental',       'severity' => 'critical', 'desc' => 'Diesel spill of approximately 40 litres at Workshop Area — not reported within 1-hour requirement.',  'days_ago' => 14, 'resolved' => true],
            ['type' => 'ppe',                 'severity' => 'low',      'desc' => 'Site visitor recorded entering operational area without high-visibility vest.',                         'days_ago' => 16, 'resolved' => true],
            ['type' => 'speed_violation',    'severity' => 'low',      'desc' => 'GRD-007 exceeded speed limit on access road by 5 km/h.',                                                'days_ago' => 18, 'resolved' => true],
            ['type' => 'overloading',        'severity' => 'medium',   'desc' => 'Bell B50E (TRK-115) recorded payload 12% over rated capacity on 3 consecutive loads.',                 'days_ago' => 20, 'resolved' => false],
            ['type' => 'maintenance_overdue', 'severity' => 'medium',  'desc' => 'EXC-002 500-hour hydraulic service overdue by 87 hours.',                                               'days_ago' => 22, 'resolved' => false],
            ['type' => 'geofence_breach',    'severity' => 'high',     'desc' => 'Haul truck TRK-118 exited designated haul corridor at coordinates -25.8912, 28.2348.',                 'days_ago' => 25, 'resolved' => true],
        ];

        foreach ($violations as $v) {
            $occurredAt = now()->subDays($v['days_ago'])->subHours(rand(0, 8));
            $resolvedAt = $v['resolved'] ? $occurredAt->copy()->addHours(rand(4, 72)) : null;

            ComplianceViolation::create([
                'team_id' => $this->team->id,
                'violation_type' => $v['type'],
                'description' => $v['desc'],
                'severity' => $v['severity'],
                'detected_at' => $occurredAt,
                'remediation_deadline' => $occurredAt->copy()->addDays(match ($v['severity']) {
                    'critical' => 1, 'high' => 3, 'medium' => 7, default => 14,
                }),
                'resolved_at' => $resolvedAt,
                'resolved_by' => $resolvedAt ? $admin->id : null,
                'resolution_notes' => $resolvedAt ? 'Issue investigated and corrective actions implemented. Operators re-briefed.' : null,
                'metadata' => json_encode(['source' => 'seeder', 'auto_detected' => rand(0, 1) === 1]),
            ]);
        }

        // Compliance reports
        if (! ComplianceReport::exists()) {
            $reportDefs = [
                ['type' => 'safety',      'days_ago' => 3,  'score' => 91.4],
                ['type' => 'safety',      'days_ago' => 7,  'score' => 88.0],
                ['type' => 'safety',      'days_ago' => 35, 'score' => 85.2],
                ['type' => 'environmental', 'days_ago' => 36, 'score' => 87.5],
                ['type' => 'production',  'days_ago' => 14, 'score' => 93.1],
                ['type' => 'equipment',   'days_ago' => 5,  'score' => 79.8],
            ];

            foreach ($reportDefs as $r) {
                ComplianceReport::create([
                    'mine_area_id' => $this->areas->isNotEmpty() ? $this->areas->random()->id : null,
                    'report_type' => $r['type'],
                    'generated_by' => $admin->id,
                    'report_date' => now()->subDays($r['days_ago'])->toDateString(),
                    'status' => 'approved',
                    'compliance_score' => $r['score'],
                    'data' => json_encode([
                        'total_violations' => rand(3, 12),
                        'resolved_violations' => rand(1, 10),
                        'critical_count' => rand(0, 2),
                        'high_count' => rand(0, 4),
                        'score' => $r['score'],
                    ]),
                    'issues' => json_encode([]),
                ]);
            }
        }

        $this->command->info('  ✓ Compliance violations (10) + reports (6) seeded.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Fuel Budgets
    // ──────────────────────────────────────────────────────────────────────────

    private function seedFuelBudgets(): void
    {
        if (FuelBudget::where('team_id', $this->team->id)->exists()) {
            $this->command->info('  ↳ Fuel budgets already seeded.');

            return;
        }

        $currentMonth = now()->startOfMonth();

        $budgets = [
            // Current month overall
            [
                'mine_area_id' => null,
                'period_type' => 'monthly',
                'start_date' => $currentMonth->toDateString(),
                'end_date' => $currentMonth->copy()->endOfMonth()->toDateString(),
                'budgeted_amount' => 380000.00,
                'budgeted_liters' => 95000,
                'actual_spent' => 267400.00,
                'actual_liters' => 66850,
                'status' => 'active',
                'notes' => 'Includes all pit equipment + support vehicles',
            ],
            // Previous month
            [
                'mine_area_id' => null,
                'period_type' => 'monthly',
                'start_date' => $currentMonth->copy()->subMonth()->startOfMonth()->toDateString(),
                'end_date' => $currentMonth->copy()->subMonth()->endOfMonth()->toDateString(),
                'budgeted_amount' => 375000.00,
                'budgeted_liters' => 93750,
                'actual_spent' => 398200.00,
                'actual_liters' => 99550,
                'status' => 'completed',
                'notes' => 'Over budget due to unexpected excavator repair hours',
            ],
            // Q2 quarterly budget
            [
                'mine_area_id' => null,
                'period_type' => 'quarterly',
                'start_date' => Carbon::create(2026, 4, 1)->toDateString(),
                'end_date' => Carbon::create(2026, 6, 30)->toDateString(),
                'budgeted_amount' => 1140000.00,
                'budgeted_liters' => 285000,
                'actual_spent' => 665600.00,
                'actual_liters' => 166400,
                'status' => 'active',
                'notes' => 'Q2 2026 fleet fuel budget',
            ],
        ];

        // Per mine area monthly budgets
        foreach ($this->areas->take(3) as $area) {
            $budgets[] = [
                'mine_area_id' => $area->id,
                'period_type' => 'monthly',
                'start_date' => $currentMonth->toDateString(),
                'end_date' => $currentMonth->copy()->endOfMonth()->toDateString(),
                'budgeted_amount' => round(rand(80000, 140000)),
                'budgeted_liters' => rand(20000, 35000),
                'actual_spent' => round(rand(50000, 110000)),
                'actual_liters' => rand(12000, 27000),
                'status' => 'active',
                'notes' => "Allocated budget for {$area->name}",
            ];
        }

        foreach ($budgets as $b) {
            FuelBudget::create(array_merge($b, ['team_id' => $this->team->id]));
        }

        $this->command->info('  ✓ Fuel budgets seeded.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Production Targets
    // ──────────────────────────────────────────────────────────────────────────

    private function seedProductionTargets(): void
    {
        if (ProductionTarget::where('team_id', $this->team->id)->exists()) {
            $this->command->info('  ↳ Production targets already seeded.');

            return;
        }

        $currentMonth = now()->startOfMonth();
        $pitAreas = $this->areas->filter(fn ($a) => str_contains(strtolower($a->name ?? ''), 'pit'));
        if ($pitAreas->isEmpty()) {
            $pitAreas = $this->areas->take(3);
        }

        // Overall monthly target
        ProductionTarget::create([
            'team_id' => $this->team->id,
            'mine_area_id' => null,
            'period_type' => 'monthly',
            'start_date' => $currentMonth->toDateString(),
            'end_date' => $currentMonth->copy()->endOfMonth()->toDateString(),
            'target_quantity' => 180000,
            'unit' => 'tonne',
            'description' => 'Total site production target — May 2026',
            'is_active' => true,
        ]);

        // Quarterly overall
        ProductionTarget::create([
            'team_id' => $this->team->id,
            'mine_area_id' => null,
            'period_type' => 'quarterly',
            'start_date' => Carbon::create(2026, 4, 1)->toDateString(),
            'end_date' => Carbon::create(2026, 6, 30)->toDateString(),
            'target_quantity' => 540000,
            'unit' => 'tonne',
            'description' => 'Q2 2026 production target',
            'is_active' => true,
        ]);

        // Per pit monthly targets
        foreach ($pitAreas as $area) {
            ProductionTarget::create([
                'team_id' => $this->team->id,
                'mine_area_id' => $area->id,
                'period_type' => 'monthly',
                'start_date' => $currentMonth->toDateString(),
                'end_date' => $currentMonth->copy()->endOfMonth()->toDateString(),
                'target_quantity' => rand(45000, 70000),
                'unit' => 'tonne',
                'description' => "Monthly ore production target — {$area->name}",
                'is_active' => true,
            ]);
        }

        // Previous month targets (for history chart)
        ProductionTarget::create([
            'team_id' => $this->team->id,
            'mine_area_id' => null,
            'period_type' => 'monthly',
            'start_date' => $currentMonth->copy()->subMonth()->startOfMonth()->toDateString(),
            'end_date' => $currentMonth->copy()->subMonth()->endOfMonth()->toDateString(),
            'target_quantity' => 175000,
            'unit' => 'tonne',
            'description' => 'Total site production target — April 2026',
            'is_active' => false,
        ]);

        $this->command->info('  ✓ Production targets seeded.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Shift Templates
    // ──────────────────────────────────────────────────────────────────────────

    private function seedShiftTemplates(): void
    {
        if (ShiftTemplate::where('team_id', $this->team->id)->exists()) {
            $this->command->info('  ↳ Shift templates already seeded.');

            return;
        }

        $admin = $this->users->first();

        $templates = [
            [
                'category' => 'shift_update',
                'title' => 'Standard Shift Handover',
                'body' => "Shift [SHIFT_TYPE] handover:\n- Loads completed: [LOADS]\n- Total tonnage: [TONNES]t\n- Active machines: [MACHINE_COUNT]\n- Operator count: [HEADCOUNT]\n- Downtime events: [DOWNTIME_EVENTS]\n\nNotes: [NOTES]",
                'fields' => ['SHIFT_TYPE', 'LOADS', 'TONNES', 'MACHINE_COUNT', 'HEADCOUNT', 'DOWNTIME_EVENTS', 'NOTES'],
            ],
            [
                'category' => 'breakdown',
                'title' => 'Machine Breakdown Report',
                'body' => "BREAKDOWN REPORT — Machine: [MACHINE_ID]\nShift: [SHIFT] | Time: [TIME]\nFault description: [FAULT]\nEstimated downtime: [ETA_HOURS] hours\nMaintenance team notified: [MAINTENANCE_NOTIFIED]\nTemporary action taken: [ACTION]",
                'fields' => ['MACHINE_ID', 'SHIFT', 'TIME', 'FAULT', 'ETA_HOURS', 'MAINTENANCE_NOTIFIED', 'ACTION'],
            ],
            [
                'category' => 'safety_alert',
                'title' => 'Safety Incident / Near-Miss Alert',
                'body' => "⚠️ SAFETY ALERT\nIncident type: [TYPE]\nLocation: [LOCATION]\nTime: [TIME]\nPersonnel involved: [PERSONS]\nDescription: [DESCRIPTION]\nImmediate actions taken: [ACTIONS]\nArea status: [AREA_STATUS]",
                'fields' => ['TYPE', 'LOCATION', 'TIME', 'PERSONS', 'DESCRIPTION', 'ACTIONS', 'AREA_STATUS'],
            ],
            [
                'category' => 'production',
                'title' => 'Production Milestone Update',
                'body' => "PRODUCTION UPDATE — [DATE]\nTarget: [TARGET]t | Achieved: [ACTUAL]t ([PCT]%)\nTop performer: [TOP_MACHINE]\nCycles completed: [CYCLES]\nFuel efficiency: [FUEL_PER_TONNE] L/t\nComments: [COMMENTS]",
                'fields' => ['DATE', 'TARGET', 'ACTUAL', 'PCT', 'TOP_MACHINE', 'CYCLES', 'FUEL_PER_TONNE', 'COMMENTS'],
            ],
            [
                'category' => 'general',
                'title' => 'General Operational Notice',
                'body' => "OPERATIONAL NOTICE — [DATE]\n[NOTICE_BODY]\n\nAction required: [ACTION_REQUIRED]\nResponsible: [RESPONSIBLE]\nDeadline: [DEADLINE]",
                'fields' => ['DATE', 'NOTICE_BODY', 'ACTION_REQUIRED', 'RESPONSIBLE', 'DEADLINE'],
            ],
            [
                'category' => 'maintenance',
                'title' => 'Scheduled Maintenance Reminder',
                'body' => "MAINTENANCE REMINDER\nMachine: [MACHINE_ID] | Service type: [SERVICE_TYPE]\nCurrent hours: [CURRENT_HOURS] | Service due at: [DUE_HOURS]\nEstimated duration: [DURATION] hours\nRequired parts: [PARTS]\nAssigned technician: [TECHNICIAN]",
                'fields' => ['MACHINE_ID', 'SERVICE_TYPE', 'CURRENT_HOURS', 'DUE_HOURS', 'DURATION', 'PARTS', 'TECHNICIAN'],
            ],
        ];

        foreach ($templates as $t) {
            ShiftTemplate::create([
                'team_id' => $this->team->id,
                'category' => $t['category'],
                'title' => $t['title'],
                'template_body' => $t['body'],
                'required_fields' => json_encode($t['fields']),
                'created_by' => $admin->id,
            ]);
        }

        $this->command->info('  ✓ Shift templates seeded (6 templates).');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // AI Insights
    // ──────────────────────────────────────────────────────────────────────────

    private function seedAiInsights(): void
    {
        if (AIInsight::where('team_id', $this->team->id)->exists()) {
            $this->command->info('  ↳ AI insights already seeded.');

            return;
        }

        $insights = [
            [
                'type' => 'fuel_optimization',
                'category' => 'efficiency',
                'severity' => 'medium',
                'title' => 'Fuel consumption 14% above fleet benchmark',
                'desc' => '3 haulers (TRK-112, TRK-115, TRK-118) are consuming 14% more fuel per tonne than the fleet average. Analysis suggests suboptimal tyre pressures and extended idle times are the primary causes. Correcting both could save ~R 42,000/month.',
                'data' => ['machines' => ['TRK-112', 'TRK-115', 'TRK-118'], 'excess_pct' => 14, 'monthly_savings_zar' => 42000],
                'viz' => ['chart_type' => 'bar', 'x' => 'machine', 'y' => 'l_per_tonne'],
                'days_ago' => 1, 'read' => false,
            ],
            [
                'type' => 'predictive_maintenance',
                'category' => 'maintenance',
                'severity' => 'high',
                'title' => 'EXC-003 hydraulic system degradation detected',
                'desc' => 'Pattern analysis of hydraulic pressure readings over 14 days shows a 0.8 bar/day pressure drop trend. Historical data from similar units suggests hydraulic seal failure within 200-350 operating hours if unaddressed. Recommend scheduling inspection within next 50 hours.',
                'data' => ['machine' => 'EXC-003', 'drop_per_day' => 0.8, 'predicted_failure_hours' => 275],
                'viz' => ['chart_type' => 'line', 'x' => 'date', 'y' => 'hydraulic_pressure_bar'],
                'days_ago' => 2, 'read' => false,
            ],
            [
                'type' => 'route_optimization',
                'category' => 'efficiency',
                'severity' => 'low',
                'title' => 'Haul road 3A reroute could improve cycle time by 8%',
                'desc' => 'GPS trace analysis shows average haul distance from North Pit to Waste Dump is 4.2 km. The alternate Route B is 3.85 km shorter and reduces grade change events. Estimated fleet-wide improvement: +8% cycle time efficiency and -6% fuel per cycle.',
                'data' => ['current_km' => 4.2, 'alternate_km' => 3.85, 'cycle_improvement_pct' => 8, 'fuel_saving_pct' => 6],
                'viz' => ['chart_type' => 'map', 'routes' => 2],
                'days_ago' => 3, 'read' => true,
            ],
            [
                'type' => 'operator_behavior',
                'category' => 'safety',
                'severity' => 'medium',
                'title' => '5 operators averaging >2 speed violations/shift this week',
                'desc' => 'Behavioural analysis of GPS and telematics data for the week of 27 May – 2 June identified 5 operators with repeated speed violations, predominantly on Haul Road 3B between coordinates -25.891, 28.233 and -25.889, 28.237. Recommend targeted re-briefing and increased camera monitoring.',
                'data' => ['operators_count' => 5, 'hotspot_road' => 'HR-3B', 'avg_violations_per_shift' => 2.4],
                'viz' => ['chart_type' => 'heatmap', 'metric' => 'speed_violations'],
                'days_ago' => 1, 'read' => false,
            ],
            [
                'type' => 'production_forecast',
                'category' => 'production',
                'severity' => 'low',
                'title' => 'On track to exceed May production target by 3.2%',
                'desc' => 'Based on current daily production rate (5,840 t/day average over last 7 days) and 26 remaining working days, the model forecasts 183,840 tonnes for May against a target of 180,000. Maintain current operational tempo.',
                'data' => ['forecast_tonnes' => 183840, 'target_tonnes' => 180000, 'daily_avg' => 5840, 'confidence' => 0.87],
                'viz' => ['chart_type' => 'area', 'x' => 'date', 'y' => 'cumulative_tonnes'],
                'days_ago' => 0, 'read' => false,
            ],
            [
                'type' => 'downtime_analysis',
                'category' => 'maintenance',
                'severity' => 'medium',
                'title' => 'Unplanned downtime up 22% vs last month',
                'desc' => 'Total unplanned downtime this month: 187 hours vs 153 hours in April. Primary drivers: hydraulic failures (68h), tyre incidents (44h), and electrical faults (35h). Recommends accelerating 500-hour hydraulic service schedule for EXC-001 and EXC-002.',
                'data' => ['current_downtime_h' => 187, 'prev_downtime_h' => 153, 'increase_pct' => 22, 'top_category' => 'hydraulic'],
                'viz' => ['chart_type' => 'pie', 'metric' => 'downtime_by_category'],
                'days_ago' => 4, 'read' => true,
            ],
        ];

        foreach ($insights as $ins) {
            AIInsight::create([
                'team_id' => $this->team->id,
                'insight_type' => $ins['type'],
                'category' => $ins['category'],
                'severity' => $ins['severity'],
                'title' => $ins['title'],
                'description' => $ins['desc'],
                'data' => json_encode($ins['data']),
                'visualization_data' => json_encode($ins['viz']),
                'is_read' => $ins['read'],
                'valid_until' => now()->addDays(14),
                'created_at' => now()->subDays($ins['days_ago']),
                'updated_at' => now()->subDays($ins['days_ago']),
            ]);
        }

        $this->command->info('  ✓ AI insights seeded (6).');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // AI Predictive Alerts
    // ──────────────────────────────────────────────────────────────────────────

    private function seedAiPredictiveAlerts(): void
    {
        if (AIPredictiveAlert::where('team_id', $this->team->id)->exists()) {
            $this->command->info('  ↳ AI predictive alerts already seeded.');

            return;
        }

        $agent = AIAgent::where('team_id', $this->team->id)->first()
            ?? AIAgent::first();
        $machine = $this->machines->first();
        $area = $this->areas->first();

        if (! $agent) {
            $this->command->warn('  ↳ No AI agent found, skipping predictive alerts.');

            return;
        }

        $alerts = [
            [
                'type' => 'component_failure',
                'severity' => 'high',
                'title' => 'EXC-003: Hydraulic pump failure predicted in 48–72 hrs',
                'desc' => 'Pressure signature analysis and vibration frequency data indicate early-stage hydraulic pump wear. Failure probability 83% within 72 hours at current operating rate.',
                'predictions' => ['failure_hours' => 60, 'confidence' => 0.83, 'component' => 'hydraulic_pump'],
                'probability' => 0.83,
                'occurrence' => now()->addHours(60),
                'actions' => ['Schedule hydraulic pump inspection immediately', 'Reduce operating hours to <8h/day until service', 'Pre-order seal kit P/N HYD-8821-A'],
                'ack' => false, 'days_ago' => 1,
            ],
            [
                'type' => 'maintenance_due',
                'severity' => 'medium',
                'title' => 'TRK-112: 500-hour engine service due in 38 hours',
                'desc' => 'Engine hour counter approaching 500-hour service milestone. Based on current daily utilisation (9.2 h/day), service will be required within 38 operating hours.',
                'predictions' => ['hours_until_due' => 38, 'service_type' => '500hr_engine', 'confidence' => 0.97],
                'probability' => 0.97,
                'occurrence' => now()->addHours(38),
                'actions' => ['Book TRK-112 into workshop for Thursday service', 'Prepare engine oil (45L), filter kit, fuel filter set'],
                'ack' => true, 'days_ago' => 0,
            ],
            [
                'type' => 'tyre_wear',
                'severity' => 'medium',
                'title' => 'TRK-115: Front tyres nearing end of service life',
                'desc' => 'Tread depth monitoring and haul cycle count analysis predict front tyre replacement needed within 12–18 days. Current estimated remaining life: 420 cycles.',
                'predictions' => ['remaining_cycles' => 420, 'days_remaining' => 15, 'position' => 'front_axle'],
                'probability' => 0.74,
                'occurrence' => now()->addDays(15),
                'actions' => ['Order replacement tyres (size 29.5R25) — lead time 5 days', 'Schedule tyre change for Week 23'],
                'ack' => false, 'days_ago' => 2,
            ],
            [
                'type' => 'fuel_anomaly',
                'severity' => 'low',
                'title' => 'TRK-118: Fuel consumption anomaly detected',
                'desc' => 'TRK-118 fuel consumption per cycle has increased 11% over the last 5 days without a corresponding change in payload or route. Possible causes: injector fouling, air filter blockage, or tyre pressure issues.',
                'predictions' => ['consumption_increase_pct' => 11, 'likely_cause' => 'injector_fouling', 'confidence' => 0.62],
                'probability' => 0.62,
                'occurrence' => now()->addDays(5),
                'actions' => ['Check air filter and replace if blocked', 'Inspect fuel injectors', 'Verify tyre pressures on all 6 wheels'],
                'ack' => false, 'days_ago' => 1,
            ],
            [
                'type' => 'production_risk',
                'severity' => 'low',
                'title' => 'Risk: Production target shortfall if downtime increases',
                'desc' => 'Monte Carlo simulation across current maintenance risk profile shows 28% probability of missing monthly target if 2+ machines go offline simultaneously in the next 10 days.',
                'predictions' => ['shortfall_probability' => 0.28, 'critical_window_days' => 10],
                'probability' => 0.28,
                'occurrence' => now()->addDays(10),
                'actions' => ['Expedite EXC-003 hydraulic service', 'Pre-position spare TRK unit on standby'],
                'ack' => false, 'days_ago' => 0,
            ],
        ];

        foreach ($alerts as $a) {
            AIPredictiveAlert::create([
                'team_id' => $this->team->id,
                'ai_agent_id' => $agent?->id,
                'alert_type' => $a['type'],
                'severity' => $a['severity'],
                'title' => $a['title'],
                'description' => $a['desc'],
                'predictions' => json_encode($a['predictions']),
                'probability' => $a['probability'],
                'predicted_occurrence' => $a['occurrence'],
                'recommended_actions' => json_encode($a['actions']),
                'related_machine_id' => $machine?->id,
                'related_mine_area_id' => $area?->id,
                'is_acknowledged' => $a['ack'],
                'acknowledged_by' => $a['ack'] ? $this->users->first()?->id : null,
                'acknowledged_at' => $a['ack'] ? now()->subDays($a['days_ago'])->addHours(2) : null,
                'was_accurate' => null,
                'created_at' => now()->subDays($a['days_ago']),
                'updated_at' => now()->subDays($a['days_ago']),
            ]);
        }

        $this->command->info('  ✓ AI predictive alerts seeded (5).');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // AI Analysis Sessions
    // ──────────────────────────────────────────────────────────────────────────

    private function seedAiAnalysisSessions(): void
    {
        if (AIAnalysisSession::where('team_id', $this->team->id)->exists()) {
            $this->command->info('  ↳ AI analysis sessions already seeded.');

            return;
        }

        $agent = AIAgent::where('team_id', $this->team->id)->first()
            ?? AIAgent::first();
        $admin = $this->users->first();

        if (! $agent) {
            $this->command->warn('  ↳ No AI agent found, skipping analysis sessions.');

            return;
        }

        $sessions = [
            [
                'type' => 'fleet_health_scan',
                'status' => 'completed',
                'params' => ['scope' => 'all_machines', 'depth' => 'full'],
                'results' => ['machines_analysed' => 25, 'issues_found' => 7, 'critical' => 1, 'score' => 82.4],
                'recs' => 4, 'time_ms' => 3420, 'days_ago' => 1,
            ],
            [
                'type' => 'fuel_efficiency_analysis',
                'status' => 'completed',
                'params' => ['period_days' => 30, 'machine_types' => ['articulated_hauler']],
                'results' => ['avg_l_per_tonne' => 1.84, 'fleet_benchmark' => 1.61, 'worst_machine' => 'TRK-112', 'potential_saving_zar' => 42000],
                'recs' => 3, 'time_ms' => 2180, 'days_ago' => 1,
            ],
            [
                'type' => 'predictive_maintenance',
                'status' => 'completed',
                'params' => ['lookahead_days' => 30, 'confidence_threshold' => 0.6],
                'results' => ['components_at_risk' => 6, 'high_probability' => 2, 'medium' => 3, 'low' => 1],
                'recs' => 5, 'time_ms' => 5810, 'days_ago' => 2,
            ],
            [
                'type' => 'production_forecast',
                'status' => 'completed',
                'params' => ['forecast_days' => 30, 'model' => 'lstm'],
                'results' => ['forecast_tonnes' => 183840, 'confidence_interval' => [179200, 188400], 'target_achieved_prob' => 0.91],
                'recs' => 2, 'time_ms' => 8940, 'days_ago' => 0,
            ],
            [
                'type' => 'operator_fatigue_risk',
                'status' => 'completed',
                'params' => ['lookback_days' => 14, 'threshold_score' => 60],
                'results' => ['high_risk_operators' => 2, 'medium_risk' => 4, 'incidents_correlated' => 1],
                'recs' => 3, 'time_ms' => 1650, 'days_ago' => 3,
            ],
            [
                'type' => 'route_optimisation',
                'status' => 'completed',
                'params' => ['haul_corridors' => ['North Pit → Waste Dump', 'South Pit → Stockpile']],
                'results' => ['cycles_analysed' => 4820, 'improvement_identified_pct' => 8, 'fuel_saving_pct' => 6],
                'recs' => 2, 'time_ms' => 4200, 'days_ago' => 3,
            ],
            [
                'type' => 'anomaly_detection',
                'status' => 'running',
                'params' => ['sensors' => 'all', 'window_hours' => 24],
                'results' => null,
                'recs' => 0, 'time_ms' => null, 'days_ago' => 0,
            ],
        ];

        foreach ($sessions as $s) {
            $startedAt = now()->subDays($s['days_ago'])->subMinutes(rand(5, 60));
            $completedAt = $s['status'] === 'completed'
                ? $startedAt->copy()->addMilliseconds($s['time_ms'] ?? 1000)
                : null;

            AIAnalysisSession::create([
                'team_id' => $this->team->id,
                'ai_agent_id' => $agent?->id,
                'user_id' => $admin->id,
                'analysis_type' => $s['type'],
                'status' => $s['status'],
                'input_parameters' => json_encode($s['params']),
                'results' => $s['results'] ? json_encode($s['results']) : null,
                'recommendations_generated' => $s['recs'],
                'processing_time_ms' => $s['time_ms'],
                'started_at' => $startedAt,
                'completed_at' => $completedAt,
                'error_message' => null,
                'created_at' => $startedAt,
                'updated_at' => $completedAt ?? $startedAt,
            ]);
        }

        $this->command->info('  ✓ AI analysis sessions seeded (7).');
    }
}
