<?php

namespace Tests\Feature;

use App\Livewire\MachineAssignmentManager;
use App\Models\Machine;
use App\Models\MachineAreaAssignment;
use App\Models\MineArea;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * App\Livewire\MachineAssignmentManager was deleted from the codebase at
 * some point (bundled into an unrelated commit) while its 5 view files
 * stayed behind, referencing 9 methods that no longer existed anywhere --
 * `php artisan livewire:verify` already flagged the missing class, and the
 * page had no route at all, so it was completely unreachable. Rebuilt using
 * the same mine_area_id + MachineAreaAssignment history pattern
 * MineAreaDetail already uses (the original views assumed a many-to-many
 * pivot that was never actually implemented). These tests cover the
 * rebuild's core behavior and its MachinePolicy authorization.
 */
class MachineAssignmentManagerTest extends TestCase
{
    use RefreshDatabase;

    private function teamWithArea(): array
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        // Admin-role users must have confirmed 2FA to pass the admin.2fa
        // middleware on the authenticated route group.
        $owner->forceFill(['current_team_id' => $team->id, 'two_factor_confirmed_at' => now()])->save();
        TeamRoleProvisioner::assignRole($owner, $team, 'admin');

        $area = MineArea::create(['team_id' => $team->id, 'name' => 'North Pit', 'status' => 'active']);

        return [$owner, $team, $area];
    }

    public function test_guest_is_redirected_away_from_assignment_manager(): void
    {
        [, $team, $area] = $this->teamWithArea();

        $response = $this->get("/mine-areas/{$area->id}/assignments");

        $response->assertRedirect('/login');
    }

    public function test_team_owner_can_view_the_assignment_manager(): void
    {
        [$owner, $team, $area] = $this->teamWithArea();

        $response = $this->actingAs($owner)->get("/mine-areas/{$area->id}/assignments");

        $response->assertOk();
        $response->assertSee('Machine Assignment');
        $response->assertSee($area->name);
    }

    public function test_a_user_from_another_team_cannot_view_the_assignment_manager(): void
    {
        [, , $area] = $this->teamWithArea();

        $otherUser = User::factory()->create();
        $otherTeam = Team::factory()->create(['user_id' => $otherUser->id]);
        $otherUser->update(['current_team_id' => $otherTeam->id]);

        $response = $this->actingAs($otherUser)->get("/mine-areas/{$area->id}/assignments");

        $response->assertNotFound();
    }

    public function test_admin_can_assign_a_machine_to_the_area_and_it_logs_history(): void
    {
        [$owner, $team, $area] = $this->teamWithArea();

        $otherArea = MineArea::create(['team_id' => $team->id, 'name' => 'South Pit', 'status' => 'active']);
        $machine = Machine::factory()->create(['team_id' => $team->id, 'mine_area_id' => $otherArea->id]);

        Livewire::actingAs($owner)
            ->test(MachineAssignmentManager::class, ['mineArea' => $area])
            ->call('assignSingleMachine', $machine->id);

        $this->assertSame($area->id, $machine->fresh()->mine_area_id);
        $this->assertDatabaseHas('machine_mine_area_assignments', [
            'machine_id' => $machine->id,
            'mine_area_id' => $area->id,
            'unassigned_at' => null,
        ]);
    }

    public function test_unassigning_a_machine_moves_it_to_another_active_area(): void
    {
        [$owner, $team, $area] = $this->teamWithArea();
        $otherArea = MineArea::create(['team_id' => $team->id, 'name' => 'South Pit', 'status' => 'active']);
        $machine = Machine::factory()->create(['team_id' => $team->id, 'mine_area_id' => $area->id]);

        Livewire::actingAs($owner)
            ->test(MachineAssignmentManager::class, ['mineArea' => $area])
            ->call('unassignMachine', $machine->id);

        $this->assertSame($otherArea->id, $machine->fresh()->mine_area_id);
    }

    public function test_unassigning_a_machine_is_blocked_when_no_other_active_area_exists(): void
    {
        [$owner, $team, $area] = $this->teamWithArea();
        $machine = Machine::factory()->create(['team_id' => $team->id, 'mine_area_id' => $area->id]);

        Livewire::actingAs($owner)
            ->test(MachineAssignmentManager::class, ['mineArea' => $area])
            ->call('unassignMachine', $machine->id);

        // No other active area exists, so the machine must stay put.
        $this->assertSame($area->id, $machine->fresh()->mine_area_id);
    }

    public function test_viewer_role_cannot_assign_a_machine(): void
    {
        [$owner, $team, $area] = $this->teamWithArea();
        $viewer = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($viewer->id);
        TeamRoleProvisioner::assignRole($viewer, $team, 'viewer');

        $otherArea = MineArea::create(['team_id' => $team->id, 'name' => 'South Pit', 'status' => 'active']);
        $machine = Machine::factory()->create(['team_id' => $team->id, 'mine_area_id' => $otherArea->id]);

        Livewire::actingAs($viewer)
            ->test(MachineAssignmentManager::class, ['mineArea' => $area])
            ->call('assignSingleMachine', $machine->id);

        $this->assertSame($otherArea->id, $machine->fresh()->mine_area_id);
    }

    public function test_history_tab_shows_completed_assignment_records(): void
    {
        [$owner, $team, $area] = $this->teamWithArea();
        $machine = Machine::factory()->create(['team_id' => $team->id, 'mine_area_id' => $area->id]);

        MachineAreaAssignment::create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'mine_area_id' => $area->id,
            'assigned_by' => $owner->id,
            'assigned_at' => now()->subDays(2),
            'unassigned_at' => now()->subDay(),
        ]);

        Livewire::actingAs($owner)
            ->test(MachineAssignmentManager::class, ['mineArea' => $area])
            ->call('switchToHistory')
            ->assertSee($machine->name);
    }
}
