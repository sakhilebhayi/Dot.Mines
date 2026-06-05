<?php

namespace Tests\Feature;

use App\Models\BellEquipmentCautionCode;
use App\Models\BellEquipmentHealthHistory;
use App\Services\Integration\BellIso15143Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BellOemIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    private function makeService(): BellIso15143Service
    {
        return new BellIso15143Service('https://api.bell.example.com/fleet', 'user', 'pass');
    }

    private function baseXml(
        float $lat = -26.023,
        float $lng = 28.938,
        float $opHours = 8376.20,
        float $idleHours = 3808.96,
        int $loadCount = 13252,
        float $fuelUsed = 170285.0,
        float $fuelLevel = 22.0,
        string $engineCondition = '',
        float $activeRegenHours = 0.0,
        string $cautionCodes = '',
    ): string {
        $engineConditionXml = $engineCondition !== '' ? "<EngineCondition>{$engineCondition}</EngineCondition>" : '';
        $activeRegenXml = $activeRegenHours > 0 ? "<CumulativeActiveRegenerationHours>{$activeRegenHours}</CumulativeActiveRegenerationHours>" : '';

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Fleet version="1" snapshotTime="2026-06-05T10:00:00Z">
  <Equipment>
    <EquipmentHeader>
      <OEMName>BELL</OEMName><Model>B50E</Model>
      <EquipmentID>EQ-001</EquipmentID>
      <SerialNumber>SN001</SerialNumber>
      <PIN>SN001</PIN>
    </EquipmentHeader>
    <Location><Latitude>{$lat}</Latitude><Longitude>{$lng}</Longitude></Location>
    <CumulativeOperatingHours>{$opHours}</CumulativeOperatingHours>
    <IdleHours>{$idleHours}</IdleHours>
    <LoadCount>{$loadCount}</LoadCount>
    <CumulativePayload>544671588</CumulativePayload>
    <DEFTankLevel>15</DEFTankLevel>
    <FuelUsed>{$fuelUsed}</FuelUsed>
    <FuelLevel>{$fuelLevel}</FuelLevel>
    <EngineStatus>Running</EngineStatus>
    <EngineNumber>ENG-001</EngineNumber>
    <TelematicDataDate>2026-06-05T09:45:00Z</TelematicDataDate>
    {$engineConditionXml}
    {$activeRegenXml}
    {$cautionCodes}
  </Equipment>
</Fleet>
XML;
    }

    #[Test]
    public function test_location_history_inserted_on_first_sync(): void
    {
        Http::fake(['*' => Http::response($this->baseXml(), 200)]);
        $this->makeService()->sync();
        $this->assertDatabaseCount('bell_equipment_location_history', 1);
        $this->assertDatabaseHas('bell_equipment_location_history', ['source' => 'snapshot']);
    }

    #[Test]
    public function test_location_history_not_duplicated_when_position_unchanged(): void
    {
        Http::fake(['*' => Http::response($this->baseXml(), 200)]);
        $service = $this->makeService();
        $service->sync();
        $service->sync();
        $this->assertDatabaseCount('bell_equipment_location_history', 1);
    }

    #[Test]
    public function test_location_history_inserted_when_machine_moves(): void
    {
        Http::fake([
            '*' => Http::sequence()
                ->push($this->baseXml(lat: -26.023, lng: 28.938), 200)
                ->push($this->baseXml(lat: -26.031, lng: 28.945), 200),
        ]);
        $service = $this->makeService();
        $service->sync();
        $service->sync();
        $this->assertDatabaseCount('bell_equipment_location_history', 2);
    }

    #[Test]
    public function test_fuel_usage_history_inserted_on_first_sync(): void
    {
        Http::fake(['*' => Http::response($this->baseXml(), 200)]);
        $this->makeService()->sync();
        $this->assertDatabaseCount('bell_equipment_fuel_usage_history', 1);
        $this->assertDatabaseHas('bell_equipment_fuel_usage_history', ['fuel_remaining_percent' => 22.0]);
    }

    #[Test]
    public function test_fuel_usage_history_skipped_when_unchanged(): void
    {
        Http::fake(['*' => Http::response($this->baseXml(), 200)]);
        $service = $this->makeService();
        $service->sync();
        $service->sync();
        $this->assertDatabaseCount('bell_equipment_fuel_usage_history', 1);
    }

    #[Test]
    public function test_operating_hours_history_inserted_on_first_sync(): void
    {
        Http::fake(['*' => Http::response($this->baseXml(), 200)]);
        $this->makeService()->sync();
        $this->assertDatabaseCount('bell_equipment_operating_hours_history', 1);
    }

    #[Test]
    public function test_idle_hours_history_inserted_on_first_sync(): void
    {
        Http::fake(['*' => Http::response($this->baseXml(), 200)]);
        $this->makeService()->sync();
        $this->assertDatabaseCount('bell_equipment_idle_hours_history', 1);
    }

    #[Test]
    public function test_load_count_history_inserted_on_first_sync(): void
    {
        Http::fake(['*' => Http::response($this->baseXml(), 200)]);
        $this->makeService()->sync();
        $this->assertDatabaseCount('bell_equipment_load_count_history', 1);
        $this->assertDatabaseHas('bell_equipment_load_count_history', ['load_count' => 13252]);
    }

    #[Test]
    public function test_health_history_inserted_on_every_sync(): void
    {
        Http::fake(['*' => Http::response($this->baseXml(), 200)]);
        $service = $this->makeService();
        $service->sync();
        $service->sync();
        $this->assertDatabaseCount('bell_equipment_health_history', 2);
    }

    #[Test]
    public function test_health_score_is_90_with_only_def_penalty(): void
    {
        // DEF = 15% → -10; no other issues
        Http::fake(['*' => Http::response($this->baseXml(engineCondition: 'OK'), 200)]);
        $this->makeService()->sync();
        $health = BellEquipmentHealthHistory::first();
        $this->assertNotNull($health);
        $this->assertEquals(90.0, (float) $health->health_score);
    }

    #[Test]
    public function test_health_score_deducts_for_engine_warning(): void
    {
        Http::fake(['*' => Http::response($this->baseXml(engineCondition: 'Warning'), 200)]);
        $this->makeService()->sync();
        $health = BellEquipmentHealthHistory::first();
        $this->assertNotNull($health);
        // DEF 15% (-10) + engine Warning (-20) = 70
        $this->assertEquals(70.0, (float) $health->health_score);
    }

    #[Test]
    public function test_health_score_deducts_for_high_regen_rate(): void
    {
        // 1000 / 8376.20 ≈ 11.9% → -20
        Http::fake(['*' => Http::response($this->baseXml(activeRegenHours: 1000.0), 200)]);
        $this->makeService()->sync();
        $health = BellEquipmentHealthHistory::first();
        $this->assertNotNull($health);
        // DEF 15% (-10) + regen > 10% (-20) = 70
        $this->assertEquals(70.0, (float) $health->health_score);
    }

    #[Test]
    public function test_caution_codes_are_inserted_from_xml(): void
    {
        $codesXml = <<<'XML'
<CautionCodes>
    <CautionCode><Code>SPN520</Code><Description>High coolant temperature</Description><Severity>Warning</Severity></CautionCode>
    <CautionCode><Code>SPN100</Code><Description>Low oil pressure</Description><Severity>Critical</Severity></CautionCode>
</CautionCodes>
XML;
        Http::fake(['*' => Http::response($this->baseXml(cautionCodes: $codesXml), 200)]);
        $this->makeService()->sync();
        $this->assertDatabaseCount('bell_equipment_caution_codes', 2);
        $this->assertDatabaseHas('bell_equipment_caution_codes', ['fault_code' => 'SPN520', 'severity' => 'Warning', 'is_active' => true]);
        $this->assertDatabaseHas('bell_equipment_caution_codes', ['fault_code' => 'SPN100', 'severity' => 'Critical', 'is_active' => true]);
    }

    #[Test]
    public function test_caution_codes_are_cleared_when_absent_from_next_snapshot(): void
    {
        $codesXml = '<CautionCodes><CautionCode><Code>SPN520</Code><Description>High coolant temp</Description><Severity>Warning</Severity></CautionCode></CautionCodes>';
        Http::fake([
            '*' => Http::sequence()
                ->push($this->baseXml(cautionCodes: $codesXml), 200)
                ->push($this->baseXml(cautionCodes: ''), 200),
        ]);
        $service = $this->makeService();
        $service->sync();
        $service->sync();
        $code = BellEquipmentCautionCode::where('fault_code', 'SPN520')->first();
        $this->assertNotNull($code);
        $this->assertFalse((bool) $code->is_active);
        $this->assertNotNull($code->cleared_at);
    }

    #[Test]
    public function test_caution_code_count_reflected_in_health_score(): void
    {
        $codesXml = '<CautionCodes>'
            .'<CautionCode><Code>C1</Code><Severity>Warning</Severity></CautionCode>'
            .'<CautionCode><Code>C2</Code><Severity>Info</Severity></CautionCode>'
            .'<CautionCode><Code>C3</Code><Severity>Info</Severity></CautionCode>'
            .'</CautionCodes>';
        Http::fake(['*' => Http::response($this->baseXml(cautionCodes: $codesXml), 200)]);
        $this->makeService()->sync();
        $health = BellEquipmentHealthHistory::first();
        $this->assertNotNull($health);
        $this->assertEquals(3, $health->caution_code_count);
        // DEF 15% (-10) + 3 codes × (-5) = 75
        $this->assertEquals(75.0, (float) $health->health_score);
    }

    #[Test]
    public function test_no_specialized_history_inserted_for_invalid_equipment(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><Fleet version="1" snapshotTime="2026-06-05T10:00:00Z"><Equipment><EquipmentHeader><EquipmentID></EquipmentID><SerialNumber>SN001</SerialNumber></EquipmentHeader></Equipment></Fleet>';
        Http::fake(['*' => Http::response($xml, 200)]);
        $this->makeService()->sync();
        $this->assertDatabaseCount('bell_equipment_location_history', 0);
        $this->assertDatabaseCount('bell_equipment_health_history', 0);
        $this->assertDatabaseCount('bell_equipment_caution_codes', 0);
    }
}
