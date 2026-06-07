<?php

namespace Tests\Feature;

use App\Models\BellEquipmentCurrentStatus;
use App\Models\BellEquipmentDailyKpi;
use App\Models\BellFleetSnapshot;
use App\Services\Integration\BellIso15143Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BellIso15143ServiceTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------------ //
    // Sample XML fixtures                                                  //
    // ------------------------------------------------------------------ //

    private function validFleetXml(string $equipmentId = 'ASA B50E#9086', string $serial = 'AEBA850EC03509086'): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Fleet version="1" snapshotTime="2026-06-02T12:54:29Z">
  <Equipment>
    <EquipmentHeader>
      <OEMName>BELL</OEMName>
      <Model>B50E</Model>
      <EquipmentID>{$equipmentId}</EquipmentID>
      <SerialNumber>{$serial}</SerialNumber>
      <PIN>{$serial}</PIN>
      <UnitInstallDateTime>2022-01-15T00:00:00Z</UnitInstallDateTime>
    </EquipmentHeader>
    <Location>
      <Latitude>-26.0231000</Latitude>
      <Longitude>28.9387000</Longitude>
    </Location>
    <CumulativeOperatingHours>8376.20</CumulativeOperatingHours>
    <IdleHours>3808.96</IdleHours>
    <LoadCount>13252</LoadCount>
    <CumulativePayload>544671588</CumulativePayload>
    <DEFTankLevel>0</DEFTankLevel>
    <Odometer>94114</Odometer>
    <FuelUsed>170285</FuelUsed>
    <FuelLevel>22</FuelLevel>
    <EngineStatus>Running</EngineStatus>
    <EngineNumber>ENG-001</EngineNumber>
    <TelematicDataDate>2026-06-02T11:14:14Z</TelematicDataDate>
  </Equipment>
</Fleet>
XML;
    }

    private function makeService(): BellIso15143Service
    {
        return new BellIso15143Service(
            'https://api.bell.example.com/fleet',
            'user',
            'pass'
        );
    }

    // ------------------------------------------------------------------ //
    // Happy path                                                           //
    // ------------------------------------------------------------------ //

    #[Test]
    public function test_successful_sync_inserts_equipment_master_and_all_related_records(): void
    {
        Http::fake([
            '*' => Http::response($this->validFleetXml(), 200, ['Content-Type' => 'application/xml']),
        ]);

        $result = $this->makeService()->sync();

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['processed']);
        $this->assertEquals(1, $result['inserted']);
        $this->assertEquals(0, $result['updated']);

        $this->assertDatabaseHas('bell_equipment', ['equipment_id' => 'ASA B50E#9086', 'model' => 'B50E']);
        $this->assertDatabaseCount('bell_equipment_current_status', 1);
        $this->assertDatabaseCount('bell_equipment_telemetry_history', 1);
        $this->assertDatabaseCount('bell_fleet_snapshots', 1);
        $this->assertDatabaseCount('bell_integration_audit_logs', 1);
        $this->assertDatabaseHas('bell_integration_audit_logs', ['success' => true, 'records_processed' => 1]);
    }

    #[Test]
    public function test_second_sync_updates_equipment_master_and_replaces_current_status(): void
    {
        Http::fake([
            '*' => Http::response($this->validFleetXml(), 200, ['Content-Type' => 'application/xml']),
        ]);

        $service = $this->makeService();
        $result1 = $service->sync();
        $result2 = $service->sync();

        $this->assertTrue($result1['success'], 'First sync failed: '.($result1['error'] ?? ''));
        $this->assertTrue($result2['success'], 'Second sync failed: '.($result2['error'] ?? ''));

        // Equipment master updated, not duplicated
        $this->assertDatabaseCount('bell_equipment', 1);
        $this->assertDatabaseHas('bell_equipment', ['equipment_id' => 'ASA B50E#9086']);

        // Current status always exactly 1 row per machine
        $this->assertDatabaseCount('bell_equipment_current_status', 1);

        // History accumulates
        $this->assertDatabaseCount('bell_equipment_telemetry_history', 2);

        // Two audit log entries
        $this->assertDatabaseCount('bell_integration_audit_logs', 2);
    }

    #[Test]
    public function test_fleet_snapshot_stores_raw_json(): void
    {
        Http::fake([
            '*' => Http::response($this->validFleetXml(), 200, ['Content-Type' => 'application/xml']),
        ]);

        $this->makeService()->sync();

        $snapshot = BellFleetSnapshot::first();
        $this->assertNotNull($snapshot);
        $this->assertEquals('1', $snapshot->fleet_version);
        $this->assertEquals(1, $snapshot->equipment_count);

        $json = json_decode($snapshot->raw_json, true);
        $this->assertArrayHasKey('SnapshotTime', $json);
        $this->assertArrayHasKey('Equipment', $json);
        $this->assertCount(1, $json['Equipment']);
    }

    #[Test]
    public function test_current_status_holds_correct_telemetry_values(): void
    {
        Http::fake([
            '*' => Http::response($this->validFleetXml(), 200, ['Content-Type' => 'application/xml']),
        ]);

        $this->makeService()->sync();

        $status = BellEquipmentCurrentStatus::first();
        $this->assertNotNull($status);
        $this->assertEquals(-26.0231000, (float) $status->latitude);
        $this->assertEquals(28.9387000, (float) $status->longitude);
        $this->assertEquals(8376.20, (float) $status->operating_hours);
        $this->assertEquals(3808.96, (float) $status->idle_hours);
        $this->assertEquals(13252, $status->load_count);
        $this->assertEquals(22, (float) $status->fuel_remaining_percent);
        $this->assertTrue($status->engine_running);
    }

    #[Test]
    public function test_daily_kpis_are_calculated_on_first_sync(): void
    {
        Http::fake([
            '*' => Http::response($this->validFleetXml(), 200, ['Content-Type' => 'application/xml']),
        ]);

        $this->makeService()->sync();

        $kpi = BellEquipmentDailyKpi::first();
        $this->assertNotNull($kpi);
        $this->assertEquals('2026-06-02', $kpi->kpi_date->toDateString());

        // Without a prior snapshot all delta fields equal the full cumulative value
        $this->assertEquals(13252, $kpi->loads_moved);
        $this->assertEquals(544671588.0, (float) $kpi->payload_moved);
        $this->assertEquals(170285.0, (float) $kpi->fuel_used);
        $this->assertEquals(94114.0, (float) $kpi->distance_travelled);

        // Utilization = 8376.20 / (8376.20 + 3808.96) * 100
        $expectedUtilization = round(8376.20 / (8376.20 + 3808.96) * 100, 2);
        $this->assertEquals($expectedUtilization, (float) $kpi->utilization_percent);
    }

    // ------------------------------------------------------------------ //
    // Validation – invalid records are skipped                            //
    // ------------------------------------------------------------------ //

    #[Test]
    public function test_record_with_missing_equipment_id_is_skipped(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Fleet version="1" snapshotTime="2026-06-02T12:54:29Z">
  <Equipment>
    <EquipmentHeader>
      <OEMName>BELL</OEMName>
      <Model>B50E</Model>
      <EquipmentID></EquipmentID>
      <SerialNumber>SN123</SerialNumber>
    </EquipmentHeader>
  </Equipment>
</Fleet>
XML;
        Http::fake(['*' => Http::response($xml, 200)]);

        $result = $this->makeService()->sync();

        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['processed']);
        $this->assertDatabaseCount('bell_equipment', 0);
    }

    #[Test]
    public function test_record_with_missing_serial_number_is_skipped(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Fleet version="1" snapshotTime="2026-06-02T12:54:29Z">
  <Equipment>
    <EquipmentHeader>
      <OEMName>BELL</OEMName>
      <Model>B50E</Model>
      <EquipmentID>EQ-001</EquipmentID>
      <SerialNumber></SerialNumber>
    </EquipmentHeader>
  </Equipment>
</Fleet>
XML;
        Http::fake(['*' => Http::response($xml, 200)]);

        $result = $this->makeService()->sync();

        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['processed']);
    }

    #[Test]
    public function test_record_with_out_of_range_latitude_is_skipped(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Fleet version="1" snapshotTime="2026-06-02T12:54:29Z">
  <Equipment>
    <EquipmentHeader>
      <OEMName>BELL</OEMName><Model>B50E</Model>
      <EquipmentID>EQ-001</EquipmentID><SerialNumber>SN001</SerialNumber>
    </EquipmentHeader>
    <Location><Latitude>999</Latitude><Longitude>28.93</Longitude></Location>
  </Equipment>
</Fleet>
XML;
        Http::fake(['*' => Http::response($xml, 200)]);

        $result = $this->makeService()->sync();

        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['processed']);
    }

    #[Test]
    public function test_record_with_fuel_remaining_above100_is_skipped(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Fleet version="1" snapshotTime="2026-06-02T12:54:29Z">
  <Equipment>
    <EquipmentHeader>
      <OEMName>BELL</OEMName><Model>B50E</Model>
      <EquipmentID>EQ-001</EquipmentID><SerialNumber>SN001</SerialNumber>
    </EquipmentHeader>
    <Location><Latitude>-26</Latitude><Longitude>28</Longitude></Location>
    <FuelLevel>150</FuelLevel>
  </Equipment>
</Fleet>
XML;
        Http::fake(['*' => Http::response($xml, 200)]);

        $result = $this->makeService()->sync();

        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['processed']);
    }

    // ------------------------------------------------------------------ //
    // Failure path                                                         //
    // ------------------------------------------------------------------ //

    #[Test]
    public function test_api_http_error_is_handled_gracefully_and_logs_audit_entry(): void
    {
        Http::fake(['*' => Http::response('Server Error', 500)]);

        $result = $this->makeService()->sync();

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);

        $this->assertDatabaseHas('bell_integration_audit_logs', ['success' => false]);
    }

    #[Test]
    public function test_malformed_xml_is_handled_gracefully_and_logs_audit_entry(): void
    {
        Http::fake(['*' => Http::response('<<not valid xml>>', 200)]);

        $result = $this->makeService()->sync();

        $this->assertFalse($result['success']);
        $this->assertDatabaseHas('bell_integration_audit_logs', ['success' => false]);
    }

    #[Test]
    public function test_empty_fleet_response_processes_zero_records(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><Fleet version="1" snapshotTime="2026-06-02T12:54:29Z"></Fleet>';
        Http::fake(['*' => Http::response($xml, 200)]);

        $result = $this->makeService()->sync();

        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['processed']);
        $this->assertDatabaseCount('bell_fleet_snapshots', 1);
        $this->assertDatabaseCount('bell_equipment', 0);
    }
}
