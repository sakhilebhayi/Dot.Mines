<?php

namespace Tests\Feature;

use App\Livewire\Fleet;
use App\Models\Machine;
use App\Models\MachineMetric;
use App\Models\ProductionRecord;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FleetListTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_away_from_fleet_page()
    {
        $response = $this->get('/fleet');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_the_fleet_list_with_their_own_machines()
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $machine = Machine::factory()->create([
            'team_id' => $team->id,
            'name' => 'Test Haul Truck 01',
        ]);

        $response = $this->actingAs($user)->get('/fleet');

        $response->assertOk();
        $response->assertSee('Test Haul Truck 01');
    }

    /**
     * Fleet cards must show each machine's real cumulative engine hours
     * from telemetry (machine_metrics.operating_hours, which the Bell
     * /Fleet snapshot sync writes on every poll) -- the latest reading,
     * per machine, never a placeholder.
     */
    public function test_fleet_cards_show_the_latest_engine_hours_from_telemetry()
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $truck = Machine::factory()->create(['team_id' => $team->id, 'name' => 'ADT 01']);
        $digger = Machine::factory()->create(['team_id' => $team->id, 'name' => 'EX 02']);

        MachineMetric::create([
            'team_id' => $team->id,
            'machine_id' => $truck->id,
            'operating_hours' => 1200.5,
            'created_at' => now()->subHour(),
        ]);
        MachineMetric::create([
            'team_id' => $team->id,
            'machine_id' => $truck->id,
            'operating_hours' => 8376.2,
        ]);
        MachineMetric::create([
            'team_id' => $team->id,
            'machine_id' => $digger->id,
            'operating_hours' => 5210.75,
        ]);

        $response = $this->actingAs($user)->get('/fleet');

        $response->assertOk();
        $response->assertSee('Engine Hours');
        // Latest reading per machine, correctly associated.
        $response->assertSee('8,376.2 hrs');
        $response->assertSee('5,210.8 hrs');
        $response->assertDontSee('1,200.5 hrs');
    }

    /**
     * A newest metric row without an hours reading must not blank out the
     * card -- the latest AVAILABLE reading wins -- and a machine with no
     * telemetry at all must render gracefully.
     */
    public function test_fleet_cards_fall_back_gracefully_without_engine_hours_telemetry()
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $tracked = Machine::factory()->create(['team_id' => $team->id, 'name' => 'ADT 03']);
        Machine::factory()->create(['team_id' => $team->id, 'name' => 'ADT 04 No Telemetry']);

        MachineMetric::create([
            'team_id' => $team->id,
            'machine_id' => $tracked->id,
            'operating_hours' => 3200.0,
            'created_at' => now()->subHour(),
        ]);
        // Newest row carries no hours (e.g. a location-only reading).
        MachineMetric::create([
            'team_id' => $team->id,
            'machine_id' => $tracked->id,
            'operating_hours' => null,
            'fuel_level' => 50,
        ]);

        $response = $this->actingAs($user)->get('/fleet');

        $response->assertOk();
        $response->assertSee('ADT 04 No Telemetry');
        $response->assertSee('3,200.0 hrs');
    }

    /**
     * The Machine Performance section used to show an identical hardcoded
     * "15% Score" for every machine (averages of columns no integration
     * writes, plus a neutral-50 fuel fallback). It must now show real
     * utilisation derived from today's telemetry deltas and real
     * production counts, labelled with the period.
     */
    public function test_machine_performance_section_shows_real_utilisation_and_production()
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $machine = Machine::factory()->create(['team_id' => $team->id, 'name' => 'ADT 07']);

        // Engine 100 -> 108 hrs, idle 20 -> 21.6 hrs today = 80% utilisation.
        MachineMetric::create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'recorded_at' => now()->startOfDay()->addHours(6),
            'operating_hours' => 100.0,
            'idle_hours' => 20.0,
        ]);
        MachineMetric::create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'recorded_at' => now()->startOfDay()->addHours(16),
            'operating_hours' => 108.0,
            'idle_hours' => 21.6,
        ]);

        ProductionRecord::create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'record_date' => now()->toDateString(),
            'shift' => 'continuous',
            'quantity_produced' => 750,
            'unit' => 'tonnes',
            'status' => 'completed',
            'metadata' => ['source' => 'telemetry', 'loads' => 150, 'cycles' => 150],
        ]);

        $response = $this->actingAs($user)->get('/fleet');

        $response->assertOk();
        $response->assertSee('Machine Performance');
        $response->assertSee('80%');
        $response->assertSee('Utilisation');
        $response->assertSee('150 loads');
        $response->assertSee('750 t');
        $response->assertSee('8.0 hrs run');
        $response->assertDontSee('Score</div>', false);
    }

    /**
     * A machine with telemetry that cannot support a utilisation figure
     * must be reported as unranked, not scored with invented numbers.
     */
    public function test_machines_with_insufficient_telemetry_are_reported_not_ranked()
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $machine = Machine::factory()->create(['team_id' => $team->id]);
        MachineMetric::create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'recorded_at' => now(),
            'operating_hours' => 5210.75,
        ]);

        $response = $this->actingAs($user)->get('/fleet');

        $response->assertOk();
        $response->assertSee('1 machine not ranked');
        $response->assertSee('insufficient telemetry today');
    }

    public function test_fleet_list_does_not_leak_another_teams_machines()
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $otherTeam = Team::factory()->create();
        Machine::factory()->create([
            'team_id' => $otherTeam->id,
            'name' => 'Other Team Excavator',
        ]);

        $response = $this->actingAs($user)->get('/fleet');

        $response->assertOk();
        $response->assertDontSee('Other Team Excavator');
    }

    public function test_manually_entered_coordinates_persist_to_the_real_location_columns(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);
        TeamRoleProvisioner::assignRole($user->fresh(), $team, 'admin');

        $machine = Machine::factory()->create([
            'team_id' => $team->id,
            'model' => 'B45E',
            'status' => 'active',
            'last_location_latitude' => null,
            'last_location_longitude' => null,
        ]);

        // The edit form wrote nonexistent latitude/longitude columns for its
        // whole life -- mass assignment silently dropped the user's
        // coordinates. This pins the fix to last_location_*.
        Livewire::actingAs($user->fresh())
            ->test(Fleet::class)
            ->call('editMachine', $machine->id)
            ->set('latitude', -26.1234)
            ->set('longitude', 28.5678)
            ->call('saveMachine')
            ->assertHasNoErrors();

        $machine->refresh();
        $this->assertEqualsWithDelta(-26.1234, $machine->last_location_latitude, 0.0001);
        $this->assertEqualsWithDelta(28.5678, $machine->last_location_longitude, 0.0001);
    }
}
