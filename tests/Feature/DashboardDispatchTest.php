<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\Geofence;
use App\Models\GeofenceEntry;
use App\Models\Machine;
use App\Models\MachineMetric;
use App\Models\Team;
use App\Models\User;
use App\Services\DispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The dashboard's Fleet Dispatch section: live states derived only from
 * real signals -- latest telemetry (speed, engine flag, freshness) and open
 * geofence entries in typed zones. Loading/dumping are never guessed
 * outside a zone of that type, and silence is reported as silence.
 */
class DashboardDispatchTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $this->team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $this->team->id]);
    }

    private function machineWithReading(array $metric = [], array $machine = []): Machine
    {
        $m = Machine::factory()->create(array_merge(['team_id' => $this->team->id, 'status' => 'active'], $machine));

        if ($metric !== []) {
            MachineMetric::factory()->create(array_merge([
                'team_id' => $this->team->id,
                'machine_id' => $m->id,
                'recorded_at' => now(),
                'speed' => 0,
            ], $metric));
        }

        return $m;
    }

    private function stateOf(Machine $machine): array
    {
        $snapshot = app(DispatchService::class)->fleetSnapshot($this->team->id);

        foreach ($snapshot['machines'] as $row) {
            if ($row['machine']->id === $machine->id) {
                return $row;
            }
        }

        $this->fail('Machine missing from dispatch snapshot.');
    }

    public function test_silence_is_reported_as_no_telemetry_never_guessed(): void
    {
        $never = $this->machineWithReading([]);
        $stale = $this->machineWithReading(['recorded_at' => now()->subHours(3)]);

        $this->assertSame('no_telemetry', $this->stateOf($never)['state']);
        $this->assertSame('no_telemetry', $this->stateOf($stale)['state']);
    }

    public function test_speed_and_engine_flags_drive_travelling_idling_and_parked(): void
    {
        $travelling = $this->machineWithReading(['speed' => 32, 'raw_data' => ['engine_running' => true]]);
        $idling = $this->machineWithReading(['speed' => 0, 'raw_data' => ['engine_running' => true]]);
        $parked = $this->machineWithReading(['speed' => 0, 'raw_data' => ['engine_running' => false]]);

        $this->assertSame('travelling', $this->stateOf($travelling)['state']);
        $this->assertSame('idling', $this->stateOf($idling)['state']);
        $this->assertSame('parked', $this->stateOf($parked)['state']);
    }

    public function test_loading_and_dumping_require_a_typed_zone(): void
    {
        $pit = Geofence::factory()->create(['team_id' => $this->team->id, 'type' => 'pit']);
        $dump = Geofence::factory()->create(['team_id' => $this->team->id, 'type' => 'dump']);

        $loader = $this->machineWithReading(['speed' => 1, 'raw_data' => ['engine_running' => true]]);
        $dumper = $this->machineWithReading(['speed' => 0, 'raw_data' => ['engine_running' => true]]);

        GeofenceEntry::factory()->create([
            'team_id' => $this->team->id,
            'machine_id' => $loader->id,
            'geofence_id' => $pit->id,
            'entry_time' => now()->subMinutes(10),
            'exit_time' => null,
        ]);
        GeofenceEntry::factory()->create([
            'team_id' => $this->team->id,
            'machine_id' => $dumper->id,
            'geofence_id' => $dump->id,
            'entry_time' => now()->subMinutes(5),
            'exit_time' => null,
        ]);

        $loaderRow = $this->stateOf($loader);
        $this->assertSame('loading', $loaderRow['state']);
        $this->assertSame($pit->name, $loaderRow['zone']);
        $this->assertSame('dumping', $this->stateOf($dumper)['state']);
    }

    public function test_counts_summarise_the_fleet(): void
    {
        $this->machineWithReading(['speed' => 20]);
        $this->machineWithReading([]);

        $counts = app(DispatchService::class)->fleetSnapshot($this->team->id)['counts'];

        $this->assertSame(1, $counts['travelling']);
        $this->assertSame(1, $counts['no_telemetry']);
    }

    public function test_movement_between_readings_is_travelling_even_without_a_speed_field(): void
    {
        // Live Bell's snapshot carries no Speed field at all -- movement
        // must be derived from consecutive positions or 'travelling'
        // stays zero forever on this fleet.
        $machine = Machine::factory()->create(['team_id' => $this->team->id, 'status' => 'active']);
        MachineMetric::factory()->create([
            'team_id' => $this->team->id, 'machine_id' => $machine->id,
            'latitude' => -26.0000, 'longitude' => 28.9000, 'speed' => null,
            'recorded_at' => now()->subMinutes(20), 'created_at' => now()->subMinutes(20),
        ]);
        MachineMetric::factory()->create([
            'team_id' => $this->team->id, 'machine_id' => $machine->id,
            // ~2.8km north in 15 minutes ~= 11 km/h
            'latitude' => -25.9750, 'longitude' => 28.9000, 'speed' => null,
            'raw_data' => ['engine_running' => true],
            'recorded_at' => now()->subMinutes(5), 'created_at' => now()->subMinutes(5),
        ]);

        $snapshot = app(DispatchService::class)->fleetSnapshot($this->team->id);

        $this->assertSame(1, $snapshot['counts']['travelling']);
        $this->assertStringContainsString('between the last two readings', $snapshot['machines'][0]['basis']);
    }

    public function test_matching_positions_between_readings_stay_idling(): void
    {
        $machine = Machine::factory()->create(['team_id' => $this->team->id, 'status' => 'active']);
        foreach ([20, 5] as $minutesAgo) {
            MachineMetric::factory()->create([
                'team_id' => $this->team->id, 'machine_id' => $machine->id,
                'latitude' => -26.0000, 'longitude' => 28.9000, 'speed' => null,
                'raw_data' => ['engine_running' => true],
                'recorded_at' => now()->subMinutes($minutesAgo), 'created_at' => now()->subMinutes($minutesAgo),
            ]);
        }

        $snapshot = app(DispatchService::class)->fleetSnapshot($this->team->id);

        $this->assertSame(1, $snapshot['counts']['idling']);
        $this->assertSame(0, $snapshot['counts']['travelling']);
    }

    public function test_a_reported_speed_field_still_outranks_derivation(): void
    {
        // If a provider DOES send speed, its own reading wins over our
        // two-point estimate.
        $machine = Machine::factory()->create(['team_id' => $this->team->id, 'status' => 'active']);
        MachineMetric::factory()->create([
            'team_id' => $this->team->id, 'machine_id' => $machine->id,
            'latitude' => -26.0000, 'longitude' => 28.9000, 'speed' => null,
            'recorded_at' => now()->subMinutes(20), 'created_at' => now()->subMinutes(20),
        ]);
        MachineMetric::factory()->create([
            'team_id' => $this->team->id, 'machine_id' => $machine->id,
            'latitude' => -25.9750, 'longitude' => 28.9000,
            'speed' => 0.0, // provider affirmatively says stationary NOW
            'raw_data' => ['engine_running' => true],
            'recorded_at' => now()->subMinutes(5), 'created_at' => now()->subMinutes(5),
        ]);

        $snapshot = app(DispatchService::class)->fleetSnapshot($this->team->id);

        $this->assertSame(0, $snapshot['counts']['travelling']);
        $this->assertSame(1, $snapshot['counts']['idling']);
    }

    public function test_a_fully_parked_fleet_reads_as_quiet_not_as_failure(): void
    {
        // A parked fleet used to render as a wall of red chips and
        // zeroes -- discouraging, and wrong: nothing is broken, the
        // shift is over.
        $this->machineWithReading(['speed' => 0, 'raw_data' => ['engine_running' => false]]);
        $this->machineWithReading(['speed' => 0, 'raw_data' => ['engine_running' => false]]);
        $user = User::where('current_team_id', $this->team->id)->firstOrFail();

        $html = Livewire::actingAs($user)->test(Dashboard::class)->html();

        $this->assertStringContainsString('Fleet is quiet', $html);
    }
}
