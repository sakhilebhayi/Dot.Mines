<?php

namespace Tests\Feature\Api;

use App\Models\Integration;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Api\IntegrationController checked team ownership (`$integration->team_id
 * === auth()->user()->current_team_id`) but nothing else -- the Livewire
 * IntegrationManager UI requires the real `manage_integrations`/
 * `sync_integrations` permission (via IntegrationPolicy) before letting a
 * user create, update, delete, test, or sync an integration, but any
 * authenticated member of the team, regardless of role, could perform every
 * one of those actions by calling this API directly. `fleet_manager` is a
 * real seeded role with `view_integrations` but not `manage_integrations` --
 * exactly the gap: someone who can only view integrations in the UI could
 * fully manage them via the API.
 */
class IntegrationControllerAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(Team $team, string $role): User
    {
        $user = User::factory()->create();
        $user->teams()->attach($team, ['role' => $role]);
        $user->update(['current_team_id' => $team->id]);
        TeamRoleProvisioner::assignRole($user, $team, $role);

        return $user;
    }

    public function test_a_user_without_manage_integrations_cannot_create_one_via_the_api(): void
    {
        $team = Team::factory()->create();
        $user = $this->userWithRole($team, 'fleet_manager'); // has view_integrations, not manage_integrations

        $response = $this->actingAs($user)->postJson('/api/v1/integrations', [
            'provider' => 'volvo',
            'name' => 'Sneaky Integration',
            'credentials' => ['api_key' => 'x', 'api_secret' => 'y'],
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('integrations', ['name' => 'Sneaky Integration']);
    }

    public function test_a_user_with_manage_integrations_can_create_one_via_the_api(): void
    {
        $team = Team::factory()->create();
        $user = $this->userWithRole($team, 'admin');

        $response = $this->actingAs($user)->postJson('/api/v1/integrations', [
            'provider' => 'volvo',
            'name' => 'Real Integration',
            'credentials' => ['api_key' => 'x', 'api_secret' => 'y'],
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('integrations', ['name' => 'Real Integration', 'team_id' => $team->id]);
    }

    public function test_a_user_without_manage_integrations_cannot_update_one_via_the_api(): void
    {
        $team = Team::factory()->create();
        $user = $this->userWithRole($team, 'fleet_manager');
        $integration = Integration::factory()->forProvider('volvo')->create(['team_id' => $team->id, 'name' => 'Original']);

        $response = $this->actingAs($user)->putJson("/api/v1/integrations/{$integration->id}", ['name' => 'Renamed']);

        $response->assertForbidden();
        $this->assertSame('Original', $integration->fresh()->name);
    }

    public function test_a_user_without_manage_integrations_cannot_delete_one_via_the_api(): void
    {
        $team = Team::factory()->create();
        $user = $this->userWithRole($team, 'fleet_manager');
        $integration = Integration::factory()->forProvider('volvo')->create(['team_id' => $team->id]);

        $response = $this->actingAs($user)->deleteJson("/api/v1/integrations/{$integration->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('integrations', ['id' => $integration->id]);
    }

    public function test_a_user_without_manage_integrations_cannot_test_the_connection_via_the_api(): void
    {
        $team = Team::factory()->create();
        $user = $this->userWithRole($team, 'fleet_manager');
        $integration = Integration::factory()->forProvider('volvo')->create(['team_id' => $team->id]);

        $response = $this->actingAs($user)->postJson("/api/v1/integrations/{$integration->id}/test");

        $response->assertForbidden();
    }

    public function test_a_user_without_manage_or_sync_integrations_cannot_trigger_a_sync_via_the_api(): void
    {
        $team = Team::factory()->create();
        $user = $this->userWithRole($team, 'viewer'); // has neither permission
        $integration = Integration::factory()->forProvider('volvo')->create(['team_id' => $team->id]);

        $response = $this->actingAs($user)->postJson("/api/v1/integrations/{$integration->id}/sync");

        $response->assertForbidden();
    }

    public function test_a_user_without_view_integrations_cannot_list_or_view_them_via_the_api(): void
    {
        $team = Team::factory()->create();
        $user = $this->userWithRole($team, 'viewer'); // no view_integrations permission
        $integration = Integration::factory()->forProvider('volvo')->create(['team_id' => $team->id]);

        $this->actingAs($user)->getJson('/api/v1/integrations')->assertForbidden();
        $this->actingAs($user)->getJson("/api/v1/integrations/{$integration->id}")->assertForbidden();
    }

    /**
     * Integration uses HasTeamFilters, which applies a global query scope --
     * route-model-binding for a cross-team ID never finds a row to bind at
     * all, so this 404s before the controller (and its authorize() calls)
     * ever run. Confirms the existing model-level tenant isolation, on top
     * of which this test class's policy checks are additional defense.
     */
    public function test_a_user_from_a_different_team_cannot_view_or_manage_an_integration_via_the_api(): void
    {
        $team = Team::factory()->create();
        $otherTeam = Team::factory()->create();
        $outsider = $this->userWithRole($otherTeam, 'admin'); // admin of a *different* team
        $integration = Integration::factory()->forProvider('volvo')->create(['team_id' => $team->id]);

        $this->actingAs($outsider)->getJson("/api/v1/integrations/{$integration->id}")->assertNotFound();
        $this->actingAs($outsider)->deleteJson("/api/v1/integrations/{$integration->id}")->assertNotFound();
        $this->assertDatabaseHas('integrations', ['id' => $integration->id]);
    }
}
