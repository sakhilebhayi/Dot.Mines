<?php

namespace Tests\Feature;

use App\Models\BellEquipment;
use App\Models\BellEquipmentCautionCode;
use App\Models\BellEquipmentCurrentStatus;
use App\Models\BellEquipmentDailyKpi;
use App\Models\Machine;
use App\Models\Team;
use App\Services\Integration\BellTeamInsightsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BellTeamInsightsServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_team_overview_groups_bell_machines_and_monthly_kpis_for_a_team(): void
    {
        $team = Team::factory()->create(['name' => 'AfriCoal SA Operations']);
        $otherTeam = Team::factory()->create();

        $machine = Machine::factory()->create([
            'team_id' => $team->id,
            'name' => 'Bell Truck 01',
            'manufacturer' => 'Bell',
            'machine_type' => 'truck',
        ]);

        $otherMachine = Machine::factory()->create([
            'team_id' => $otherTeam->id,
            'name' => 'Bell Truck 02',
            'manufacturer' => 'Bell',
            'machine_type' => 'truck',
        ]);

        $bellEquipment = BellEquipment::create([
            'machine_id' => $machine->id,
            'equipment_id' => 'ASA-B50E-001',
            'oem_name' => 'BELL',
            'model' => 'B50E',
            'serial_number' => 'SN-001',
        ]);

        $otherBellEquipment = BellEquipment::create([
            'machine_id' => $otherMachine->id,
            'equipment_id' => 'ASA-B50E-002',
            'oem_name' => 'BELL',
            'model' => 'B50E',
            'serial_number' => 'SN-002',
        ]);

        BellEquipmentCurrentStatus::create([
            'equipment_key' => $bellEquipment->equipment_key,
            'snapshot_time' => Carbon::create(2026, 5, 15, 12, 0, 0),
            'latitude' => -26.1,
            'longitude' => 28.2,
            'operating_hours' => 126.5,
            'load_count' => 42,
            'fuel_remaining_percent' => 36.5,
            'engine_running' => true,
            'updated_date' => Carbon::create(2026, 5, 15, 12, 0, 0),
        ]);

        BellEquipmentCautionCode::create([
            'equipment_key' => $bellEquipment->equipment_key,
            'fault_code' => 'SPN520',
            'fault_description' => 'Engine warning',
            'severity' => 'Critical',
            'source' => 'bell',
            'is_active' => true,
            'occurred_at' => Carbon::create(2026, 5, 15, 10, 0, 0),
        ]);

        BellEquipmentCautionCode::create([
            'equipment_key' => $bellEquipment->equipment_key,
            'fault_code' => 'SPN521',
            'fault_description' => 'Resolved issue',
            'severity' => 'Low',
            'source' => 'bell',
            'is_active' => false,
            'occurred_at' => Carbon::create(2026, 5, 14, 10, 0, 0),
            'cleared_at' => Carbon::create(2026, 5, 15, 8, 0, 0),
        ]);

        BellEquipmentDailyKpi::create([
            'equipment_key' => $bellEquipment->equipment_key,
            'kpi_date' => Carbon::create(2026, 5, 15),
            'loads_moved' => 42,
            'payload_moved' => 18450.75,
            'operating_hours' => 12.5,
            'idle_hours' => 2.5,
            'distance_travelled' => 168.4,
            'fuel_used' => 540.2,
            'utilization_percent' => 83.3,
            'created_date' => Carbon::create(2026, 5, 15, 13, 0, 0),
        ]);

        BellEquipmentDailyKpi::create([
            'equipment_key' => $otherBellEquipment->equipment_key,
            'kpi_date' => Carbon::create(2026, 5, 15),
            'loads_moved' => 80,
            'payload_moved' => 20000,
            'operating_hours' => 20,
            'idle_hours' => 1,
            'distance_travelled' => 200,
            'fuel_used' => 600,
            'utilization_percent' => 95,
            'created_date' => Carbon::create(2026, 5, 15, 13, 0, 0),
        ]);

        $service = app(BellTeamInsightsService::class);
        $overview = $service->getTeamOverview($team->id, Carbon::create(2026, 5, 1));

        $this->assertSame(1, $overview['totals']['machines']);
        $this->assertSame(1, $overview['totals']['running']);
        $this->assertSame(1, $overview['totals']['issues']);
        $this->assertSame(42, $overview['totals']['monthly_loads']);
        $this->assertEqualsWithDelta(18450.75, $overview['totals']['monthly_payload'], 0.01);
        $this->assertEqualsWithDelta(540.2, $overview['totals']['monthly_fuel'], 0.01);

        $this->assertCount(1, $overview['machines']);
        $this->assertSame('Bell Truck 01', $overview['machines'][0]['machine_name']);
        $this->assertSame(42, $overview['machines'][0]['load_count']);
        $this->assertSame(36.5, $overview['machines'][0]['fuel_remaining_percent']);
        $this->assertSame(42, $overview['machines'][0]['monthly_loads']);
        $this->assertSame(1, count($overview['machines'][0]['open_caution_codes']));
        $this->assertSame('SPN520', $overview['machines'][0]['open_caution_codes'][0]);
    }
}
