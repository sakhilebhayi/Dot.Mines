<?php

namespace Tests\Feature\Api;

use App\Models\Machine;
use App\Models\MachineAreaAssignment;
use App\Models\MineArea;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R9 audit coverage: these two endpoints fataled for their entire life
 * (they queried a mineAreas() relation and a mine_area_machine pivot that
 * never existed) and were rewritten onto the real machine_area_assignments
 * ledger in the psalm burn -- with no test proving the rewrite. This is
 * that test.
 */
class MachineAssignmentEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        return $user->fresh();
    }

    public function test_available_lists_only_machines_without_an_open_assignment(): void
    {
        $user = $this->actingUser();
        $team = $user->currentTeam;
        $area = MineArea::factory()->create(['team_id' => $team->id]);

        $free = Machine::factory()->create(['team_id' => $team->id, 'name' => 'FREE-1']);
        $released = Machine::factory()->create(['team_id' => $team->id, 'name' => 'RELEASED-1']);
        $occupied = Machine::factory()->create(['team_id' => $team->id, 'name' => 'OCCUPIED-1']);

        MachineAreaAssignment::create([
            'team_id' => $team->id,
            'machine_id' => $released->id,
            'mine_area_id' => $area->id,
            'assigned_at' => now()->subDays(3),
            'unassigned_at' => now()->subDay(),
        ]);
        MachineAreaAssignment::create([
            'team_id' => $team->id,
            'machine_id' => $occupied->id,
            'mine_area_id' => $area->id,
            'assigned_at' => now()->subDay(),
            'unassigned_at' => null,
        ]);

        $response = $this->actingAs($user)->getJson('/api/assignments/available');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('FREE-1'));
        $this->assertTrue($names->contains('RELEASED-1'), 'A closed assignment must not keep a machine out of the pool.');
        $this->assertFalse($names->contains('OCCUPIED-1'), 'An open assignment means the machine is not available.');
    }

    public function test_history_returns_the_assignment_ledger_for_a_machine(): void
    {
        $user = $this->actingUser();
        $team = $user->currentTeam;
        $area = MineArea::factory()->create(['team_id' => $team->id, 'name' => 'North Pit']);
        $machine = Machine::factory()->create(['team_id' => $team->id]);

        MachineAreaAssignment::create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'mine_area_id' => $area->id,
            'assigned_at' => now()->subDays(5),
            'unassigned_at' => now()->subDays(2),
            'notes' => 'Wet-season rotation',
        ]);

        $response = $this->actingAs($user)->getJson("/api/assignments/machines/{$machine->id}/history");

        $response->assertOk();
        $rows = $response->json();
        $this->assertCount(1, $rows);
        $this->assertSame('North Pit', $rows[0]['name']);
        $this->assertSame('Wet-season rotation', $rows[0]['notes']);
        $this->assertNotNull($rows[0]['assigned_at']);
        $this->assertNotNull($rows[0]['unassigned_at']);
    }
}
