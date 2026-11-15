<?php

namespace Tests\Feature;

use App\Livewire\GeofenceManager;
use App\Models\Geofence;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A geofence search matches on name OR description. The OR must sit inside its own group: ungrouped beside the
 * team filter it matched another team's geofence by its description (the same defect the production search had).
 */
class GeofenceSearchTenantScopeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);
        TeamRoleProvisioner::assignRole($user, $team, 'admin');

        return $user->fresh();
    }

    public function test_the_manager_search_never_matches_another_teams_geofence_description(): void
    {
        $user = $this->admin();
        Geofence::factory()->create(['team_id' => $user->current_team_id, 'name' => 'Own pit', 'description' => 'plain']);
        Geofence::factory()->create(['team_id' => Team::factory()->create()->id, 'name' => 'Rival yard', 'description' => 'zzleakmarker rival secret']);

        Livewire::actingAs($user)->test(GeofenceManager::class)
            ->set('search', 'zzleakmarker')
            ->assertDontSee('Rival yard')
            ->assertDontSee('Own pit');

        Livewire::actingAs($user)->test(GeofenceManager::class)
            ->set('search', 'plain')
            ->assertSee('Own pit')
            ->assertDontSee('Rival yard');
    }

    public function test_the_api_search_never_matches_another_teams_geofence_description(): void
    {
        $user = $this->admin();
        Geofence::factory()->create(['team_id' => $user->current_team_id, 'name' => 'Own pit', 'description' => 'plain']);
        Geofence::factory()->create(['team_id' => Team::factory()->create()->id, 'name' => 'Rival yard', 'description' => 'zzleakmarker rival secret']);

        $rows = $this->actingAs($user)->getJson('/api/v1/geofences?search=zzleakmarker')->assertOk()->json('data');
        $this->assertSame([], $rows);

        $own = $this->actingAs($user)->getJson('/api/v1/geofences?search=plain')->assertOk()->json('data');
        $this->assertCount(1, $own);
    }
}
