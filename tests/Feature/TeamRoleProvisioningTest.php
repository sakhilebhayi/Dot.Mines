<?php

namespace Tests\Feature;

use App\Actions\Fortify\CreateNewUser;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression test for the dormant-RBAC finding: before TeamRoleProvisioner
 * existed, no role/permission rows were ever created for a team unless
 * someone manually ran `php artisan db:seed --class=RolePermissionSeeder`
 * (not part of the default DatabaseSeeder, and never invoked from real
 * registration). Every hasPermission() check -- including for a team's own
 * owner -- returned false. This proves registration now bootstraps roles
 * and grants the creator 'admin', and that Settings::updateUserRole()'s
 * re-role path scopes correctly to a single team.
 */
class TeamRoleProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_registering_a_new_user_grants_them_admin_on_their_personal_team(): void
    {
        $user = (new CreateNewUser)->create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'Password12345!',
            'password_confirmation' => 'Password12345!',
            'terms' => true,
        ]);

        $team = $user->ownedTeams()->first();

        $this->assertNotNull($team);
        $this->assertTrue($user->fresh()->roles()->where('roles.team_id', $team->id)->where('roles.name', 'admin')->exists());

        $user->current_team_id = $team->id;
        $this->assertTrue($user->hasPermission('delete_machines'));
    }

    public function test_assign_role_only_touches_the_given_teams_role_not_other_teams(): void
    {
        $user = User::factory()->create();
        $teamA = Team::factory()->create(['user_id' => $user->id]);
        $teamB = Team::factory()->create(['user_id' => $user->id]);

        TeamRoleProvisioner::assignRole($user, $teamA, 'admin');
        TeamRoleProvisioner::assignRole($user, $teamB, 'viewer');

        $this->assertTrue($user->roles()->where('roles.team_id', $teamA->id)->where('roles.name', 'admin')->exists());
        $this->assertTrue($user->roles()->where('roles.team_id', $teamB->id)->where('roles.name', 'viewer')->exists());

        // Re-roling in team A must not disturb the team B assignment.
        TeamRoleProvisioner::assignRole($user, $teamA, 'operator');

        $this->assertFalse($user->roles()->where('roles.team_id', $teamA->id)->where('roles.name', 'admin')->exists());
        $this->assertTrue($user->roles()->where('roles.team_id', $teamA->id)->where('roles.name', 'operator')->exists());
        $this->assertTrue($user->roles()->where('roles.team_id', $teamB->id)->where('roles.name', 'viewer')->exists());
    }
}
