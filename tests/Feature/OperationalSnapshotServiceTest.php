<?php

namespace Tests\Feature;

use App\Models\Machine;
use App\Models\MachineMetric;
use App\Models\ProductionRecord;
use App\Models\Team;
use App\Services\OperationalSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Operational data program P3 (brief §14): one machine, one source of
 * truth. Today's loads/cycles/tonnes are counter arithmetic on real Bell
 * cumulative values -- freshest counter reading minus yesterday's stored
 * close -- and every missing input yields null ("Awaiting API data"),
 * never an invented number (§6).
 */
class OperationalSnapshotServiceTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private Machine $machine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = Team::factory()->create(['timezone' => 'Africa/Johannesburg']);
        $this->machine = Machine::factory()->create(['team_id' => $this->team->id]);
    }

    private function service(): OperationalSnapshotService
    {
        return app(OperationalSnapshotService::class);
    }

    private function yesterdayClose(float $loadEnd, float $payloadEnd): void
    {
        ProductionRecord::create([
            'team_id' => $this->team->id,
            'machine_id' => $this->machine->id,
            'record_date' => now('Africa/Johannesburg')->subDay()->toDateString(),
            'shift' => 'continuous',
            'quantity_produced' => 100,
            'unit' => 'tonnes',
            'status' => 'completed',
            'metadata' => [
                'source' => 'telemetry',
                'cumulative_load_count_end' => $loadEnd,
                'cumulative_payload_end' => $payloadEnd,
                'payload_units' => 'kilogram',
            ],
        ]);
    }

    public function test_today_counters_are_live_value_minus_yesterday_close(): void
    {
        $this->yesterdayClose(16461, 648041300);

        MachineMetric::factory()->create([
            'team_id' => $this->team->id,
            'machine_id' => $this->machine->id,
            'recorded_at' => now(),
            'raw_data' => ['load_count' => 16500, 'cumulative_payload' => 649000000, 'payload_units' => 'kilogram'],
        ]);

        $snapshot = $this->service()->forTeam($this->team)->get($this->machine->id);

        $this->assertSame(39, $snapshot['loads_today']);
        $this->assertSame(39, $snapshot['cycles_today']);
        $this->assertEqualsWithDelta(958.7, $snapshot['tonnes_today'], 0.01);
        $this->assertSame('live', $snapshot['freshness']);
    }

    public function test_missing_baseline_yields_null_not_a_guess(): void
    {
        MachineMetric::factory()->create([
            'team_id' => $this->team->id,
            'machine_id' => $this->machine->id,
            'recorded_at' => now(),
            'raw_data' => ['load_count' => 16500, 'cumulative_payload' => 649000000],
        ]);

        $snapshot = $this->service()->forTeam($this->team)->get($this->machine->id);

        $this->assertNull($snapshot['loads_today']);
        $this->assertNull($snapshot['tonnes_today']);
        $this->assertSame(16500, $snapshot['lifetime_loads']);
    }

    public function test_missing_live_counters_yield_null(): void
    {
        $this->yesterdayClose(16461, 648041300);

        MachineMetric::factory()->create([
            'team_id' => $this->team->id,
            'machine_id' => $this->machine->id,
            'recorded_at' => now(),
            'raw_data' => [],
        ]);

        $snapshot = $this->service()->forTeam($this->team)->get($this->machine->id);

        $this->assertNull($snapshot['loads_today']);
        $this->assertNull($snapshot['tonnes_today']);
    }

    public function test_todays_record_wins_when_its_reading_is_newer_than_the_metric(): void
    {
        $this->yesterdayClose(16461, 648041300);

        MachineMetric::factory()->create([
            'team_id' => $this->team->id,
            'machine_id' => $this->machine->id,
            'recorded_at' => now()->subHours(3),
            'raw_data' => ['load_count' => 16480, 'cumulative_payload' => 648500000, 'payload_units' => 'kilogram'],
        ]);

        ProductionRecord::create([
            'team_id' => $this->team->id,
            'machine_id' => $this->machine->id,
            'record_date' => now('Africa/Johannesburg')->toDateString(),
            'shift' => 'continuous',
            'quantity_produced' => 20,
            'unit' => 'tonnes',
            'status' => 'completed',
            'metadata' => [
                'source' => 'telemetry',
                'cumulative_load_count_end' => 16490,
                'cumulative_payload_end' => 648900000,
                'payload_units' => 'kilogram',
                'last_reading_utc' => now()->subHour()->toIso8601String(),
            ],
        ]);

        $snapshot = $this->service()->forTeam($this->team)->get($this->machine->id);

        $this->assertSame(29, $snapshot['loads_today'], 'The production sync read the counter more recently than the fleet snapshot.');
        $this->assertEqualsWithDelta(858.7, $snapshot['tonnes_today'], 0.01);
    }

    public function test_counter_reset_clamps_to_zero_never_negative(): void
    {
        $this->yesterdayClose(16461, 648041300);

        MachineMetric::factory()->create([
            'team_id' => $this->team->id,
            'machine_id' => $this->machine->id,
            'recorded_at' => now(),
            'raw_data' => ['load_count' => 12, 'cumulative_payload' => 500000, 'payload_units' => 'kilogram'],
        ]);

        $snapshot = $this->service()->forTeam($this->team)->get($this->machine->id);

        $this->assertSame(0, $snapshot['loads_today']);
        $this->assertSame(0.0, $snapshot['tonnes_today']);
    }

    public function test_freshness_degrades_with_telemetry_age(): void
    {
        // No connected integration -> default 300s interval -> stale after 900s.
        MachineMetric::factory()->create([
            'team_id' => $this->team->id,
            'machine_id' => $this->machine->id,
            'recorded_at' => now()->subSeconds(1000),
            'raw_data' => [],
        ]);

        $snapshot = $this->service()->forTeam($this->team)->get($this->machine->id);
        $this->assertSame('recent', $snapshot['freshness']);

        MachineMetric::factory()->create([
            'team_id' => $this->team->id,
            'machine_id' => $this->machine->id,
            'recorded_at' => now()->subSeconds(5000),
            'raw_data' => [],
        ]);
        // latestMetric is newest by created_at/id -- the fresher row above
        // still wins, so age the machine by removing it.
        MachineMetric::where('machine_id', $this->machine->id)->orderBy('id')->first()?->delete();

        $snapshot = $this->service()->forTeam($this->team)->get($this->machine->id);
        $this->assertSame('stale', $snapshot['freshness']);

        $machineWithoutMetrics = Machine::factory()->create(['team_id' => $this->team->id]);
        $snapshot = $this->service()->forTeam($this->team)->get($machineWithoutMetrics->id);
        $this->assertSame('none', $snapshot['freshness']);
        $this->assertNull($snapshot['last_telemetry_at']);
    }

    public function test_team_freshest_telemetry_timestamp(): void
    {
        $this->assertNull($this->service()->teamTelemetryFreshestAt($this->team));

        MachineMetric::factory()->create([
            'team_id' => $this->team->id,
            'machine_id' => $this->machine->id,
            'recorded_at' => now()->subMinutes(30),
        ]);
        $other = Machine::factory()->create(['team_id' => $this->team->id]);
        MachineMetric::factory()->create([
            'team_id' => $this->team->id,
            'machine_id' => $other->id,
            'recorded_at' => now()->subMinutes(5),
        ]);

        $freshest = $this->service()->teamTelemetryFreshestAt($this->team);

        $this->assertNotNull($freshest);
        $this->assertEqualsWithDelta(now()->subMinutes(5)->getTimestamp(), $freshest->getTimestamp(), 2);
    }
}
