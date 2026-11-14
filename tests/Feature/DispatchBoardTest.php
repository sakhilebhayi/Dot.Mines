<?php

namespace Tests\Feature;

use App\Models\Machine;
use App\Models\MachineMetric;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The dashboard's fleet section (formerly the pit-cycle lanes): one table
 * row per machine, driven by DispatchService's conservative real-data
 * classification -- DashboardDispatchTest covers the classification
 * itself; this covers the board.
 */
class DispatchBoardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatch_renders_one_row_per_machine_with_state_and_a_link_through(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $machine = Machine::factory()->create([
            'team_id' => $user->currentTeam->id,
            'name' => 'ADT-42',
            'status' => 'active',
        ]);
        // Fresh telemetry at haul speed => classified travelling.
        MachineMetric::factory()->create([
            'machine_id' => $machine->id,
            'team_id' => $user->currentTeam->id,
            'speed' => 22,
            'recorded_at' => now()->subMinutes(2),
            'created_at' => now()->subMinutes(2),
        ]);
        Machine::factory()->create(['team_id' => $user->currentTeam->id, 'name' => 'Silent-1', 'status' => 'active']);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Fleet now');
        $response->assertSee('ADT-42');
        $response->assertSee('Travelling');
        $response->assertSee('22 km/h');
        $response->assertSee('No telemetry');
        $response->assertSee('Silent-1');
        // The row links through to the machine without losing context.
        $response->assertSee('fleet/'.$machine->id, false);
        // The old pit-cycle lanes are gone.
        $response->assertDontSee('Loading Area');
        $response->assertDontSee('Haul Route');
        $response->assertDontSee('Dump Area');
    }

    public function test_an_empty_fleet_says_so_with_one_next_step(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('No machines in the fleet yet.');
        $response->assertSee(route('fleet'), false);
    }
}
