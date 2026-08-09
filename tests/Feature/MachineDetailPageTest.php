<?php

namespace Tests\Feature;

use App\Models\Machine;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The /fleet/{machine} page had no feature test coverage before this file.
 * Added while re-theming resources/views/livewire/machine-detail.blade.php.
 */
class MachineDetailPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_away_from_machine_detail(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $machine = Machine::factory()->create(['team_id' => $team->id]);

        $response = $this->get("/fleet/{$machine->id}");

        $response->assertRedirect('/login');
    }

    public function test_team_owner_can_view_machine_detail(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $owner->update(['current_team_id' => $team->id]);
        $machine = Machine::factory()->create(['team_id' => $team->id]);

        $response = $this->actingAs($owner)->get("/fleet/{$machine->id}");

        $response->assertOk();
        $response->assertSee($machine->name);
    }

    public function test_a_user_from_another_team_cannot_view_the_machine(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $machine = Machine::factory()->create(['team_id' => $team->id]);

        $otherUser = User::factory()->create();
        $otherTeam = Team::factory()->create(['user_id' => $otherUser->id]);
        $otherUser->update(['current_team_id' => $otherTeam->id]);

        // Machine uses HasTeamFilters (a global query scope), so the route's
        // implicit model binding never finds a cross-team machine in the
        // first place -- the request 404s before MachineDetail::mount()'s
        // own explicit 403 check would even run.
        $response = $this->actingAs($otherUser)->get("/fleet/{$machine->id}");

        $response->assertNotFound();
    }
}
