<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SyncBellProductionRecordsJob;
use App\Models\BellEquipment;
use App\Models\BellEquipmentDailyKpi;
use App\Models\Machine;
use App\Models\ProductionRecord;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SyncBellProductionRecordsJobTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------------ //
    // Helpers                                                              //
    // ------------------------------------------------------------------ //

    private function makeBellEquipment(Machine $machine, string $equipmentId = 'EQ-001'): BellEquipment
    {
        return BellEquipment::create([
            'machine_id' => $machine->id,
            'oem_name' => 'BELL',
            'model' => 'B50E',
            'equipment_id' => $equipmentId,
            'serial_number' => 'SN-'.$equipmentId,
            'pin' => 'PIN-'.$equipmentId,
        ]);
    }

    private function makeDailyKpi(
        BellEquipment $bellEq,
        string $date,
        float $payloadMoved = 50000,
        int $loadsMoved = 10,
        float $operatingHours = 8.0,
    ): BellEquipmentDailyKpi {
        return BellEquipmentDailyKpi::create([
            'equipment_key' => $bellEq->equipment_key,
            'kpi_date' => $date,
            'loads_moved' => $loadsMoved,
            'payload_moved' => $payloadMoved,
            'operating_hours' => $operatingHours,
            'idle_hours' => 1.0,
            'distance_travelled' => 120.5,
            'fuel_used' => 200.0,
            'utilization_percent' => 75.0,
            'created_date' => now(),
        ]);
    }

    private function configureTeam(Team $team): void
    {
        config(['integrations.bell.team_id' => $team->id]);
    }

    // ------------------------------------------------------------------ //
    // Configuration guard                                                  //
    // ------------------------------------------------------------------ //

    #[Test]
    public function it_skips_when_bell_team_id_is_not_configured(): void
    {
        config(['integrations.bell.team_id' => 0]);

        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn ($msg) => str_contains($msg, 'BELL_TEAM_ID not configured'));

        (new SyncBellProductionRecordsJob)->handle();

        $this->assertDatabaseCount('production_records', 0);
    }

    #[Test]
    public function it_skips_when_no_bell_equipment_is_linked_to_machines(): void
    {
        $team = Team::factory()->create();
        $this->configureTeam($team);

        // BellEquipment exists but machine_id is null
        BellEquipment::create([
            'machine_id' => null,
            'equipment_id' => 'EQ-UNLINKED',
        ]);

        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn ($msg) => str_contains($msg, 'no linked Bell machines found'));

        (new SyncBellProductionRecordsJob)->handle();

        $this->assertDatabaseCount('production_records', 0);
    }

    // ------------------------------------------------------------------ //
    // Happy path                                                           //
    // ------------------------------------------------------------------ //

    #[Test]
    public function it_creates_production_records_from_bell_daily_kpis(): void
    {
        $team = Team::factory()->create();
        $machine = Machine::factory()->create(['team_id' => $team->id]);
        $bellEq = $this->makeBellEquipment($machine);
        $this->configureTeam($team);

        $yesterday = Carbon::yesterday()->toDateString();
        $this->makeDailyKpi($bellEq, $yesterday, payloadMoved: 150000, loadsMoved: 30);

        (new SyncBellProductionRecordsJob(lookbackDays: 1))->handle();

        $this->assertDatabaseHas('production_records', [
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'shift' => 'oem_auto',
            'unit' => 'tonnes',
            'status' => 'completed',
        ]);

        $record = ProductionRecord::where('machine_id', $machine->id)->firstOrFail();
        // 150000 kg → 150 tonnes
        $this->assertEquals(150.0, (float) $record->quantity_produced);
        $this->assertEquals(150.0, (float) $record->system_quantity);
    }

    #[Test]
    public function it_converts_payload_from_kg_to_tonnes(): void
    {
        $team = Team::factory()->create();
        $machine = Machine::factory()->create(['team_id' => $team->id]);
        $bellEq = $this->makeBellEquipment($machine);
        $this->configureTeam($team);

        // 75500 kg = 75.500 tonnes
        $this->makeDailyKpi($bellEq, Carbon::yesterday()->toDateString(), payloadMoved: 75500);

        (new SyncBellProductionRecordsJob(lookbackDays: 1))->handle();

        $record = ProductionRecord::where('machine_id', $machine->id)->firstOrFail();
        $this->assertEquals(75.5, (float) $record->quantity_produced);
    }

    #[Test]
    public function it_stores_bell_metadata_on_production_record(): void
    {
        $team = Team::factory()->create();
        $machine = Machine::factory()->create(['team_id' => $team->id]);
        $bellEq = $this->makeBellEquipment($machine, 'METATEST-EQ');
        $this->configureTeam($team);

        $this->makeDailyKpi($bellEq, Carbon::yesterday()->toDateString(), payloadMoved: 10000, loadsMoved: 5);

        (new SyncBellProductionRecordsJob(lookbackDays: 1))->handle();

        $record = ProductionRecord::where('machine_id', $machine->id)->firstOrFail();
        $meta = $record->metadata;

        $this->assertIsArray($meta);
        $this->assertEquals('bell_oem_kpi', $meta['source']);
        $this->assertEquals($bellEq->equipment_key, $meta['bell_equipment_key']);
        $this->assertEquals(5, $meta['loads_moved']);
    }

    // ------------------------------------------------------------------ //
    // Zero-production skip                                                 //
    // ------------------------------------------------------------------ //

    #[Test]
    public function it_skips_kpi_rows_with_zero_loads_and_payload(): void
    {
        $team = Team::factory()->create();
        $machine = Machine::factory()->create(['team_id' => $team->id]);
        $bellEq = $this->makeBellEquipment($machine);
        $this->configureTeam($team);

        $this->makeDailyKpi($bellEq, Carbon::yesterday()->toDateString(), payloadMoved: 0, loadsMoved: 0);

        (new SyncBellProductionRecordsJob(lookbackDays: 1))->handle();

        $this->assertDatabaseCount('production_records', 0);
    }

    #[Test]
    public function it_creates_record_when_only_loads_is_zero_but_payload_is_nonzero(): void
    {
        $team = Team::factory()->create();
        $machine = Machine::factory()->create(['team_id' => $team->id]);
        $bellEq = $this->makeBellEquipment($machine);
        $this->configureTeam($team);

        // loads_moved = 0 but payload_moved > 0 — should NOT be skipped
        $this->makeDailyKpi($bellEq, Carbon::yesterday()->toDateString(), payloadMoved: 5000, loadsMoved: 0);

        (new SyncBellProductionRecordsJob(lookbackDays: 1))->handle();

        $this->assertDatabaseCount('production_records', 1);
    }

    // ------------------------------------------------------------------ //
    // Upsert behaviour                                                     //
    // ------------------------------------------------------------------ //

    #[Test]
    public function it_updates_existing_oem_auto_production_record_on_re_run(): void
    {
        $team = Team::factory()->create();
        $machine = Machine::factory()->create(['team_id' => $team->id]);
        $bellEq = $this->makeBellEquipment($machine);
        $this->configureTeam($team);

        $yesterday = Carbon::yesterday()->toDateString();
        $kpi = $this->makeDailyKpi($bellEq, $yesterday, payloadMoved: 20000);

        // First run
        (new SyncBellProductionRecordsJob(lookbackDays: 1))->handle();
        $this->assertDatabaseCount('production_records', 1);

        // Simulate updated KPI (payload increased)
        $kpi->update(['payload_moved' => 30000]);

        // Second run — should update, not duplicate
        (new SyncBellProductionRecordsJob(lookbackDays: 1))->handle();
        $this->assertDatabaseCount('production_records', 1);

        $record = ProductionRecord::where('machine_id', $machine->id)->firstOrFail();
        // 30000 kg → 30 tonnes
        $this->assertEquals(30.0, (float) $record->quantity_produced);
    }

    #[Test]
    public function it_restores_and_updates_soft_deleted_production_record(): void
    {
        $team = Team::factory()->create();
        $machine = Machine::factory()->create(['team_id' => $team->id]);
        $bellEq = $this->makeBellEquipment($machine);
        $this->configureTeam($team);

        $yesterday = Carbon::yesterday()->toDateString();
        $this->makeDailyKpi($bellEq, $yesterday, payloadMoved: 40000);

        // First run — creates record
        (new SyncBellProductionRecordsJob(lookbackDays: 1))->handle();

        // Soft-delete the record
        ProductionRecord::where('machine_id', $machine->id)->delete();
        $this->assertSoftDeleted('production_records', ['machine_id' => $machine->id]);

        // Second run — should restore the soft-deleted record
        (new SyncBellProductionRecordsJob(lookbackDays: 1))->handle();

        $this->assertDatabaseCount('production_records', 1);
        $record = ProductionRecord::where('machine_id', $machine->id)->firstOrFail();
        $this->assertNull($record->deleted_at);
        $this->assertEquals(40.0, (float) $record->quantity_produced);
    }

    // ------------------------------------------------------------------ //
    // Multi-machine / lookback                                             //
    // ------------------------------------------------------------------ //

    #[Test]
    public function it_syncs_multiple_machines(): void
    {
        $team = Team::factory()->create();
        $machine1 = Machine::factory()->create(['team_id' => $team->id]);
        $machine2 = Machine::factory()->create(['team_id' => $team->id]);
        $bellEq1 = $this->makeBellEquipment($machine1, 'EQ-MULTI-1');
        $bellEq2 = $this->makeBellEquipment($machine2, 'EQ-MULTI-2');
        $this->configureTeam($team);

        $yesterday = Carbon::yesterday()->toDateString();
        $this->makeDailyKpi($bellEq1, $yesterday, payloadMoved: 10000);
        $this->makeDailyKpi($bellEq2, $yesterday, payloadMoved: 20000);

        (new SyncBellProductionRecordsJob(lookbackDays: 1))->handle();

        $this->assertDatabaseCount('production_records', 2);
        $this->assertDatabaseHas('production_records', ['machine_id' => $machine1->id]);
        $this->assertDatabaseHas('production_records', ['machine_id' => $machine2->id]);
    }

    #[Test]
    public function it_skips_bell_equipment_with_no_machine_link(): void
    {
        $team = Team::factory()->create();
        $machine = Machine::factory()->create(['team_id' => $team->id]);
        $bellEq = $this->makeBellEquipment($machine);
        $this->configureTeam($team);

        // Unlinked equipment — machine_id is null
        $orphanEq = BellEquipment::create([
            'machine_id' => null,
            'equipment_id' => 'EQ-ORPHAN',
        ]);

        $yesterday = Carbon::yesterday()->toDateString();
        $this->makeDailyKpi($bellEq, $yesterday, payloadMoved: 10000);
        // KPI for orphan equipment — should be ignored entirely
        BellEquipmentDailyKpi::create([
            'equipment_key' => $orphanEq->equipment_key,
            'kpi_date' => $yesterday,
            'loads_moved' => 5,
            'payload_moved' => 5000,
            'operating_hours' => 4.0,
            'idle_hours' => 0.5,
            'distance_travelled' => 50.0,
            'fuel_used' => 100.0,
            'utilization_percent' => 50.0,
            'created_date' => now(),
        ]);

        (new SyncBellProductionRecordsJob(lookbackDays: 1))->handle();

        // Only 1 record for the linked machine
        $this->assertDatabaseCount('production_records', 1);
        $this->assertDatabaseHas('production_records', ['machine_id' => $machine->id]);
    }

    #[Test]
    public function it_only_syncs_kpis_within_the_lookback_window(): void
    {
        $team = Team::factory()->create();
        $machine = Machine::factory()->create(['team_id' => $team->id]);
        $bellEq = $this->makeBellEquipment($machine);
        $this->configureTeam($team);

        // KPI from 10 days ago (outside the 3-day lookback window)
        $oldDate = Carbon::today()->subDays(10)->toDateString();
        $newDate = Carbon::yesterday()->toDateString();

        $this->makeDailyKpi($bellEq, $oldDate, payloadMoved: 99000);
        $this->makeDailyKpi($bellEq, $newDate, payloadMoved: 11000);

        (new SyncBellProductionRecordsJob(lookbackDays: 3))->handle();

        // Only the record within the 3-day window should be created
        $this->assertDatabaseCount('production_records', 1);
        $record = ProductionRecord::where('machine_id', $machine->id)->firstOrFail();
        $this->assertEquals($newDate, $record->record_date->toDateString());
    }

    // ------------------------------------------------------------------ //
    // Job configuration                                                    //
    // ------------------------------------------------------------------ //

    #[Test]
    public function it_has_correct_job_configuration(): void
    {
        $job = new SyncBellProductionRecordsJob;

        $this->assertEquals(3, $job->tries);
        $this->assertEquals(120, $job->timeout);
        $this->assertNotEmpty($job->backoff);
    }

    #[Test]
    public function it_logs_completion_summary(): void
    {
        $team = Team::factory()->create();
        $machine = Machine::factory()->create(['team_id' => $team->id]);
        $bellEq = $this->makeBellEquipment($machine);
        $this->configureTeam($team);

        $this->makeDailyKpi($bellEq, Carbon::yesterday()->toDateString(), payloadMoved: 5000);

        Log::shouldReceive('debug')->zeroOrMoreTimes();
        Log::shouldReceive('info')
            ->with('SyncBellProductionRecordsJob completed', \Mockery::on(function ($ctx) {
                return isset($ctx['kpi_synced']) && $ctx['kpi_synced'] === 1
                    && isset($ctx['intraday_synced'])
                    && isset($ctx['skipped']);
            }))
            ->once();

        (new SyncBellProductionRecordsJob(lookbackDays: 1))->handle();
    }
}
