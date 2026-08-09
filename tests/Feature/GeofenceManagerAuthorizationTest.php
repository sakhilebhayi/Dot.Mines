<?php

namespace Tests\Feature;

use App\Livewire\GeofenceManager;
use App\Models\Geofence;
use App\Models\MineArea;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * GeofenceManager::saveGeofence()/deleteGeofence() now authorize against
 * GeofencePolicy (already defined but unused). Also regression-covers the
 * missing BrowserEventBridge trait fix -- without it, every successful save
 * or delete threw BadMethodCallException on the dispatchBrowserEvent() call.
 */
class GeofenceManagerAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_role_cannot_delete_a_geofence(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $viewer = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($viewer->id);
        TeamRoleProvisioner::assignRole($viewer, $team, 'viewer');

        $geofence = Geofence::factory()->create(['team_id' => $team->id]);

        Livewire::actingAs($viewer)
            ->test(GeofenceManager::class)
            ->call('deleteGeofence', $geofence->id);

        $this->assertDatabaseHas('geofences', ['id' => $geofence->id]);
    }

    public function test_fleet_manager_can_create_a_geofence(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $manager = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($manager->id);
        TeamRoleProvisioner::assignRole($manager, $team, 'fleet_manager');

        $mineArea = MineArea::create(['team_id' => $team->id, 'name' => 'Test Area', 'status' => 'active']);

        Livewire::actingAs($manager)
            ->test(GeofenceManager::class)
            ->set('mineAreaId', $mineArea->id)
            ->set('name', 'North Pit')
            ->set('type', 'pit')
            ->set('centerLatitude', -25.5)
            ->set('centerLongitude', 28.1)
            ->call('saveGeofence');

        $this->assertDatabaseHas('geofences', ['name' => 'North Pit', 'team_id' => $team->id]);
    }
}
