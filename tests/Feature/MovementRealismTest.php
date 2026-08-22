<?php

namespace Tests\Feature;

use App\Livewire\GeofenceManager;
use App\Livewire\LiveMap;
use App\Models\Geofence;
use App\Models\Machine;
use App\Models\MachineMetric;
use App\Models\MineArea;
use App\Models\Team;
use App\Models\User;
use App\Services\GeofenceSuggestionService;
use App\Services\Integration\IntegrationService;
use App\Services\Integration\ManufacturerRegistry;
use App\Services\Integration\TelemetryProductionCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Operational data program P6 (brief §9-§11, §19): movement surfaces built
 * only from real recorded GPS; zone suggestions derived from dwell history
 * and always user-confirmed; repeated provider readings stored once.
 */
class MovementRealismTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->withPersonalTeam()->create();
        $this->team = $this->user->currentTeam;
    }

    private function dwellCluster(Machine $machine, float $lat, float $lng, int $readings, int $daysBack = 3): void
    {
        for ($i = 0; $i < $readings; $i++) {
            MachineMetric::factory()->create([
                'team_id' => $this->team->id,
                'machine_id' => $machine->id,
                'latitude' => $lat + ($i % 3) * 0.00005,
                'longitude' => $lng + ($i % 2) * 0.00005,
                'speed' => 0.5,
                'recorded_at' => now()->subDays($i % $daysBack)->subMinutes($i * 7),
                'raw_data' => ['engine_running' => true],
            ]);
        }
    }

    public function test_repeated_dwell_with_engine_on_becomes_a_zone_suggestion(): void
    {
        $machineA = Machine::factory()->create(['team_id' => $this->team->id]);
        $machineB = Machine::factory()->create(['team_id' => $this->team->id]);
        $this->dwellCluster($machineA, -25.9845, 28.9105, 6);
        $this->dwellCluster($machineB, -25.9845, 28.9105, 6);

        $suggestions = app(GeofenceSuggestionService::class)->suggestForTeam($this->team);

        $this->assertCount(1, $suggestions);
        $this->assertSame(12, $suggestions[0]['readings']);
        $this->assertSame(2, $suggestions[0]['machines']);
        $this->assertGreaterThanOrEqual(2, $suggestions[0]['days']);
        $this->assertEqualsWithDelta(-25.9845, $suggestions[0]['center_latitude'], 0.001);
        $this->assertCount(4, $suggestions[0]['coordinates']);
    }

    public function test_engine_off_dwell_is_parking_not_a_zone(): void
    {
        $machine = Machine::factory()->create(['team_id' => $this->team->id]);

        for ($i = 0; $i < 10; $i++) {
            MachineMetric::factory()->create([
                'team_id' => $this->team->id,
                'machine_id' => $machine->id,
                'latitude' => -25.9848,
                'longitude' => 28.9107,
                'speed' => 0,
                'recorded_at' => now()->subDays($i % 4)->subMinutes($i * 11),
                'raw_data' => ['engine_running' => false],
            ]);
        }

        $this->assertSame([], app(GeofenceSuggestionService::class)->suggestForTeam($this->team));
    }

    public function test_moving_readings_do_not_cluster(): void
    {
        $machine = Machine::factory()->create(['team_id' => $this->team->id]);

        for ($i = 0; $i < 10; $i++) {
            MachineMetric::factory()->create([
                'team_id' => $this->team->id,
                'machine_id' => $machine->id,
                'latitude' => -25.9848,
                'longitude' => 28.9107,
                'speed' => 25,
                'recorded_at' => now()->subDays($i % 4)->subMinutes($i * 11),
                'raw_data' => ['engine_running' => true],
            ]);
        }

        $this->assertSame([], app(GeofenceSuggestionService::class)->suggestForTeam($this->team));
    }

    public function test_spots_already_covered_by_a_geofence_are_not_re_suggested(): void
    {
        $machine = Machine::factory()->create(['team_id' => $this->team->id]);
        $this->dwellCluster($machine, -25.9845, 28.9105, 8);

        Geofence::factory()->create([
            'team_id' => $this->team->id,
            'center_latitude' => -25.9846,
            'center_longitude' => 28.9106,
        ]);

        $this->assertSame([], app(GeofenceSuggestionService::class)->suggestForTeam($this->team));
    }

    public function test_using_a_suggestion_prefills_the_form_but_creates_nothing(): void
    {
        MineArea::factory()->create(['team_id' => $this->team->id, 'status' => 'active']);
        $machine = Machine::factory()->create(['team_id' => $this->team->id]);
        $this->dwellCluster($machine, -25.9845, 28.9105, 8);

        Livewire::actingAs($this->user)
            ->test(GeofenceManager::class)
            ->assertSee('Suggested zones from GPS history')
            ->call('useSuggestion', 0)
            ->assertSet('showCreateModal', true)
            ->assertSet('centerLatitude', fn ($value) => abs($value - -25.9845) < 0.001)
            ->assertSet('name', 'Activity hotspot 1');

        $this->assertDatabaseCount('geofences', 0);
    }

    public function test_trails_are_deduped_real_points_in_reading_order(): void
    {
        $machine = Machine::factory()->create(['team_id' => $this->team->id, 'name' => 'ADT-9']);

        foreach ([[1, -25.9845, 28.9105], [2, -25.9845, 28.9105], [3, -25.9860, 28.9120], [4, -25.9875, 28.9140]] as [$minutesAgoFactor, $lat, $lng]) {
            MachineMetric::factory()->create([
                'team_id' => $this->team->id,
                'machine_id' => $machine->id,
                'latitude' => $lat,
                'longitude' => $lng,
                'recorded_at' => now()->subMinutes(100 - $minutesAgoFactor * 10),
            ]);
        }

        $trails = Livewire::actingAs($this->user)->test(LiveMap::class)->instance()->getTrails();

        $this->assertCount(1, $trails);
        $this->assertSame('ADT-9', $trails[0]['name']);
        $this->assertCount(3, $trails[0]['points'], 'The repeated reading collapses; only distinct real positions remain.');
        $this->assertSame(-25.9845, $trails[0]['points'][0]['lat']);
        $this->assertSame(-25.9875, $trails[0]['points'][2]['lat']);
    }

    public function test_identical_provider_readings_are_stored_once(): void
    {
        $machine = Machine::factory()->create(['team_id' => $this->team->id]);
        $readingTime = now()->subMinutes(10);

        $service = new class extends IntegrationService
        {
            public function __construct()
            {
                parent::__construct(
                    app(ManufacturerRegistry::class),
                    app(TelemetryProductionCalculator::class),
                );
            }

            /** @param array<string, mixed> $metrics */
            public function exposedSyncMachineMetrics(Machine $machine, array $metrics): void
            {
                $this->syncMachineMetrics($machine, $metrics);
            }
        };

        $metrics = ['recorded_at' => $readingTime, 'latitude' => -25.98, 'longitude' => 28.91, 'fuel_level' => 40];

        $service->exposedSyncMachineMetrics($machine, $metrics);
        $service->exposedSyncMachineMetrics($machine, $metrics);

        $this->assertSame(1, MachineMetric::where('machine_id', $machine->id)->count());
    }
}
