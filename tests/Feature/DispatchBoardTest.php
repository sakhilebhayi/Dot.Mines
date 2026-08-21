<?php

namespace Tests\Feature;

use App\Models\Machine;
use App\Models\MachineMetric;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Slice 6 of the live-operations UX program: the dashboard dispatch
 * section renders the pit cycle as visual lanes (Loading Area -> Haul
 * Route -> Dump Area, plus the off-cycle strip) with one clickable chip
 * per machine, driven by DispatchService's conservative real-data
 * classification -- DashboardDispatchTest covers the classification
 * itself; this covers the board.
 */
class DispatchBoardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatch_renders_pit_cycle_lanes_with_clickable_machine_chips(): void
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

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Loading Area');
        $response->assertSee('Haul Route');
        $response->assertSee('Dump Area');
        $response->assertSee('No telemetry');
        $response->assertSee('ADT-42');
        // The chip links through to the machine without losing context.
        $response->assertSee('fleet/'.$machine->id, false);
        // The old table is gone -- lanes replaced it.
        $response->assertDontSee('<th class="text-left px-3 py-2', false);
    }

    public function test_empty_lanes_say_none_right_now_instead_of_vanishing(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        Machine::factory()->create([
            'team_id' => $user->currentTeam->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        // A machine with no telemetry sits in the off-cycle strip; the
        // three cycle lanes stay visible and honestly empty.
        $response->assertSee('None right now');
    }
}
