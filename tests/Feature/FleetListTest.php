<?php

namespace Tests\Feature;

use App\Models\Machine;
use App\Models\MachineMetric;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
