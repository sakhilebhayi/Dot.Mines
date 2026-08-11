<?php

namespace Tests\Feature;

use App\Livewire\MineAreaManager;
use App\Models\MineArea;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression test: MineArea had no Policy at all -- every other core model
 * (Machine, Alert, Geofence, Report, Integration) got one this session, but
 * MineAreaManager's create/update/delete actions never called authorize(),
 * so any team member of any role could create, edit, or delete mine areas.
 */
class MineAreaAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function teamWithRole(string $roleName): array
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user->id);
        TeamRoleProvisioner::assignRole($user, $team, $roleName);

        return [$team, $user];
    }

    public function test_viewer_cannot_create_a_mine_area(): void
    {
        [$team, $viewer] = $this->teamWithRole('viewer');

        Livewire::actingAs($viewer)
            ->test(MineAreaManager::class)
            ->set('name', 'New Pit')
            ->set('status', 'active')
            ->call('saveMineArea')
            ->assertStatus(403);

        $this->assertDatabaseMissing('mine_areas', ['name' => 'New Pit']);
    }

    public function test_fleet_manager_can_create_a_mine_area(): void
    {
        [$team, $manager] = $this->teamWithRole('fleet_manager');

        Livewire::actingAs($manager)
            ->test(MineAreaManager::class)
            ->set('name', 'New Pit')
            ->set('status', 'active')
            ->call('saveMineArea');

        $this->assertDatabaseHas('mine_areas', ['team_id' => $team->id, 'name' => 'New Pit']);
    }

    public function test_viewer_cannot_delete_a_mine_area(): void
    {
        [$team, $viewer] = $this->teamWithRole('viewer');
        $mineArea = MineArea::create(['team_id' => $team->id, 'name' => 'Pit 1', 'status' => 'active']);

        Livewire::actingAs($viewer)
            ->test(MineAreaManager::class)
            ->call('deleteMineArea', $mineArea->id)
            ->assertStatus(403);

        $this->assertDatabaseHas('mine_areas', ['id' => $mineArea->id]);
    }
}
