<?php

namespace Tests\Feature;

use App\Livewire\IntegrationManager;
use App\Models\Integration;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * IntegrationManager::deleteIntegration()/syncMachines() now authorize
 * against IntegrationPolicy (already defined but unused). fleet_manager only
 * gets view_integrations, not manage_integrations/sync_integrations, so it's
 * a useful role for this test -- it can see integrations but not touch them.
 */
class IntegrationManagerAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_fleet_manager_cannot_delete_an_integration(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $manager = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($manager->id);
        TeamRoleProvisioner::assignRole($manager, $team, 'fleet_manager');

        $integration = Integration::factory()->create(['team_id' => $team->id]);

        Livewire::actingAs($manager)
            ->test(IntegrationManager::class)
            ->call('deleteIntegration', $integration->id);

        $this->assertDatabaseHas('integrations', ['id' => $integration->id]);
    }

    public function test_fleet_manager_cannot_sync_an_integration(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $manager = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($manager->id);
        TeamRoleProvisioner::assignRole($manager, $team, 'fleet_manager');

        $integration = Integration::factory()->create(['team_id' => $team->id, 'last_sync_status' => null]);

        Livewire::actingAs($manager)
            ->test(IntegrationManager::class)
            ->call('syncMachines', $integration->id);

        $this->assertDatabaseHas('integrations', ['id' => $integration->id, 'last_sync_status' => null]);
    }

    public function test_admin_can_delete_an_integration(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $owner->update(['current_team_id' => $team->id]);
        TeamRoleProvisioner::assignRole($owner, $team, 'admin');

        $integration = Integration::factory()->create(['team_id' => $team->id]);

        Livewire::actingAs($owner)
            ->test(IntegrationManager::class)
            ->call('deleteIntegration', $integration->id);

        $this->assertDatabaseMissing('integrations', ['id' => $integration->id]);
    }
}
