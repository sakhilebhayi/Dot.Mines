<?php

namespace Tests\Feature;

use App\Models\Machine;
use App\Models\Role;
use App\Models\User;
use App\Services\TeamRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamRoleProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_provision_team_creates_all_roles_and_permissions(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        TeamRoleService::provisionTeam($team);

        $roles = Role::where('team_id', $team->id)->pluck('name')->toArray();

        $this->assertContains('admin', $roles);
        $this->assertContains('fleet_manager', $roles);
        $this->assertContains('operator', $roles);
        $this->assertContains('viewer', $roles);

        $adminRole = Role::where('team_id', $team->id)->where('name', 'admin')->first();
        $this->assertNotEmpty($adminRole->permissions()->get());
    }

    public function test_provision_team_assigns_admin_role_to_owner(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        TeamRoleService::provisionTeam($team, $user);

        $adminRole = Role::where('team_id', $team->id)->where('name', 'admin')->firstOrFail();

        $this->assertTrue(
            $user->roles()->where('roles.id', $adminRole->id)->exists(),
            'Owner should be assigned the admin role after provisioning'
        );
    }

    public function test_user_can_create_machines_after_admin_role_assigned(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        TeamRoleService::provisionTeam($team, $user);
        $this->actingAs($user);

        $this->assertTrue(
            $user->can('create', Machine::class),
            'User with admin role should be able to create machines'
        );
    }

    public function test_roles_are_team_scoped_so_two_teams_each_get_their_own_roles(): void
    {
        $user1 = User::factory()->withPersonalTeam()->create();
        $user2 = User::factory()->withPersonalTeam()->create();

        TeamRoleService::provisionTeam($user1->currentTeam, $user1);
        TeamRoleService::provisionTeam($user2->currentTeam, $user2);

        $adminForTeam1 = Role::where('team_id', $user1->currentTeam->id)->where('name', 'admin')->first();
        $adminForTeam2 = Role::where('team_id', $user2->currentTeam->id)->where('name', 'admin')->first();

        $this->assertNotNull($adminForTeam1, 'Team 1 should have its own admin role');
        $this->assertNotNull($adminForTeam2, 'Team 2 should have its own admin role');
        $this->assertNotEquals(
            $adminForTeam1->id,
            $adminForTeam2->id,
            'Each team should have a distinct admin role record'
        );
    }

    public function test_provision_team_is_idempotent(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        TeamRoleService::provisionTeam($team, $user);
        TeamRoleService::provisionTeam($team, $user); // second call should not throw or duplicate

        $adminRoleCount = Role::where('team_id', $team->id)->where('name', 'admin')->count();
        $this->assertEquals(1, $adminRoleCount, 'Provisioning twice should not create duplicate roles');
    }
}
