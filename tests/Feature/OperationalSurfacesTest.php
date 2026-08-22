<?php

namespace Tests\Feature;

use App\Livewire\Fleet;
use App\Livewire\MachineDetail;
use App\Livewire\ProductionDashboard;
use App\Models\Machine;
use App\Models\MachineMetric;
use App\Models\ProductionRecord;
use App\Models\Team;
use App\Models\User;
use App\Services\OperationalSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Operational data program P4: the Fleet cards, Machine Detail, and the
 * Production page all read today's loads/cycles/tonnes from the SAME
 * operational snapshot (brief §14), and a machine without counter data
 * says "awaiting API data" instead of showing an invented zero (§6).
 */
class OperationalSurfacesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $team;

    private Machine $machine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->withPersonalTeam()->create();
        $this->team = $this->user->currentTeam;
        $this->team->update(['timezone' => 'Africa/Johannesburg']);
        $this->machine = Machine::factory()->create([
            'team_id' => $this->team->id,
            'name' => 'ADT-777',
            'status' => 'active',
        ]);
    }

    private function counterData(): void
    {
        ProductionRecord::create([
            'team_id' => $this->team->id,
            'machine_id' => $this->machine->id,
            'record_date' => now('Africa/Johannesburg')->subDay()->toDateString(),
            'shift' => 'continuous',
            'quantity_produced' => 900,
            'unit' => 'tonnes',
            'status' => 'completed',
            'metadata' => [
                'source' => 'telemetry',
                'cumulative_load_count_end' => 16461,
                'cumulative_payload_end' => 648041300,
                'payload_units' => 'kilogram',
            ],
        ]);

        MachineMetric::factory()->create([
            'team_id' => $this->team->id,
            'machine_id' => $this->machine->id,
            'recorded_at' => now(),
            'raw_data' => ['load_count' => 16500, 'cumulative_payload' => 649000000, 'payload_units' => 'kilogram'],
        ]);
    }

    public function test_fleet_card_shows_todays_loads_and_tonnes_from_the_snapshot(): void
    {
        $this->counterData();

        Livewire::actingAs($this->user)
            ->test(Fleet::class)
            ->assertSee('Loads Today:')
            ->assertSee('39')
            ->assertSee('958.7');
    }

    public function test_fleet_card_says_awaiting_api_data_without_a_baseline(): void
    {
        Livewire::actingAs($this->user)
            ->test(Fleet::class)
            ->assertSee('Loads Today:')
            ->assertSee('awaiting API data');
    }

    public function test_machine_detail_production_today_section(): void
    {
        $this->counterData();

        Livewire::actingAs($this->user)
            ->test(MachineDetail::class, ['machine' => $this->machine])
            ->assertSee('Production Today')
            ->assertSee('39')
            ->assertSee('958.7')
            ->assertSee('16,500 loads')
            ->assertSee('Measured against the counter close of');
    }

    public function test_machine_detail_awaits_api_data_when_counters_are_missing(): void
    {
        Livewire::actingAs($this->user)
            ->test(MachineDetail::class, ['machine' => $this->machine])
            ->assertSee('Production Today')
            ->assertSee('Awaiting API data');
    }

    public function test_production_page_live_today_strip_totals_the_fleet(): void
    {
        $this->counterData();

        Livewire::actingAs($this->user)
            ->test(ProductionDashboard::class)
            ->assertSee('Live Today')
            ->assertSee('39')
            ->assertSee('958.7')
            ->assertSee('1 of 1 machines reporting counters');
    }

    public function test_production_page_live_strip_admits_when_nothing_reports(): void
    {
        Livewire::actingAs($this->user)
            ->test(ProductionDashboard::class)
            ->assertSee('Live Today')
            ->assertSee('Awaiting API data');
    }

    public function test_for_machine_matches_the_team_snapshot(): void
    {
        $this->counterData();

        $service = app(OperationalSnapshotService::class);

        $fromTeam = $service->forTeam($this->team)->get($this->machine->id);
        $single = $service->forMachine($this->machine);

        $this->assertSame($fromTeam['loads_today'], $single['loads_today']);
        $this->assertSame($fromTeam['tonnes_today'], $single['tonnes_today']);
        $this->assertSame($fromTeam['lifetime_loads'], $single['lifetime_loads']);
    }
}
