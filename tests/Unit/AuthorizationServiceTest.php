<?php

namespace Tests\Unit;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\AuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthorizationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeTeamWithUser(): array
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id, 'personal_team' => true]);
        $user->forceFill(['current_team_id' => $team->id])->save();

        return [$user, $team];
    }

    private function makeAdminRole(Team $team): Role
    {
        return Role::firstOrCreate(
            ['name' => 'admin'],
            ['display_name' => 'Admin', 'team_id' => $team->id]
        );
    }

    private function makeRole(Team $team, string $name = 'operator'): Role
    {
        return Role::firstOrCreate(
            ['name' => $name],
            ['display_name' => ucfirst($name), 'team_id' => $team->id]
        );
    }

    private function makePermission(Team $team, string $name = 'machines.view'): Permission
    {
        return Permission::create([
            'team_id' => $team->id,
            'name' => $name,
            'display_name' => ucwords(str_replace('.', ' ', $name)),
        ]);
    }

    // --- can() ---

    #[Test]
    public function can_returns_false_for_null_user(): void
    {
        $this->assertFalse(AuthorizationService::can(null, 'machines.view'));
    }

    #[Test]
    public function can_returns_false_when_no_team_id(): void
    {
        $user = User::factory()->create(['current_team_id' => null]);

        $this->assertFalse(AuthorizationService::can($user, 'machines.view'));
    }

    #[Test]
    public function admin_can_do_everything(): void
    {
        [$user, $team] = $this->makeTeamWithUser();
        $role = $this->makeAdminRole($team);
        $user->roles()->attach($role->id);

        $this->assertTrue(AuthorizationService::can($user, 'machines.view'));
        $this->assertTrue(AuthorizationService::can($user, 'reports.delete'));
        $this->assertTrue(AuthorizationService::can($user, 'any.arbitrary.permission'));
    }

    #[Test]
    public function user_with_permission_returns_true(): void
    {
        [$user, $team] = $this->makeTeamWithUser();
        $role = $this->makeRole($team, 'operator');
        $permission = $this->makePermission($team, 'machines.view');
        $role->permissions()->attach($permission->id);
        $user->roles()->attach($role->id);

        $this->assertTrue(AuthorizationService::can($user, 'machines.view'));
    }

    #[Test]
    public function user_without_permission_returns_false(): void
    {
        [$user, $team] = $this->makeTeamWithUser();
        $role = $this->makeRole($team, 'operator');
        $user->roles()->attach($role->id);
        // No permissions attached to role

        $this->assertFalse(AuthorizationService::can($user, 'reports.delete'));
    }

    #[Test]
    public function cross_team_access_is_denied(): void
    {
        [$user, $team] = $this->makeTeamWithUser();
        $otherTeam = Team::factory()->create(['user_id' => $user->id, 'personal_team' => false]);
        $permission = $this->makePermission($otherTeam, 'machines.view');

        $role = Role::create(['team_id' => $otherTeam->id, 'name' => 'operator-other', 'display_name' => 'Operator']);
        $role->permissions()->attach($permission->id);
        // User is NOT a member of otherTeam, and current_team_id is $team->id

        $this->assertFalse(AuthorizationService::can($user, 'machines.view', $team->id));
    }

    #[Test]
    public function explicit_team_id_overrides_current_team(): void
    {
        [$user, $team] = $this->makeTeamWithUser();
        $otherTeam = Team::factory()->create(['user_id' => $user->id, 'personal_team' => false]);
        $role = Role::create(['team_id' => $otherTeam->id, 'name' => 'manager', 'display_name' => 'Manager']);
        $permission = $this->makePermission($otherTeam, 'reports.view');
        $role->permissions()->attach($permission->id);
        $user->roles()->attach($role->id);
        $user->forceFill(['current_team_id' => $otherTeam->id])->save();

        $this->assertTrue(AuthorizationService::can($user, 'reports.view', $otherTeam->id));
    }

    // --- getRolePermissions() ---

    #[Test]
    public function get_role_permissions_returns_attached_permissions(): void
    {
        [$user, $team] = $this->makeTeamWithUser();
        $role = $this->makeRole($team, 'fleet-manager');
        $p1 = $this->makePermission($team, 'fleet.view');
        $p2 = $this->makePermission($team, 'fleet.edit');
        $role->permissions()->attach([$p1->id, $p2->id]);

        $permissions = AuthorizationService::getRolePermissions($role);

        $this->assertCount(2, $permissions);
        $this->assertTrue($permissions->pluck('name')->contains('fleet.view'));
        $this->assertTrue($permissions->pluck('name')->contains('fleet.edit'));
    }

    #[Test]
    public function get_role_permissions_accepts_role_name_string(): void
    {
        [$user, $team] = $this->makeTeamWithUser();
        $role = Role::create(['team_id' => $team->id, 'name' => 'viewer', 'display_name' => 'Viewer']);
        $perm = $this->makePermission($team, 'dashboard.view');
        $role->permissions()->attach($perm->id);

        $permissions = AuthorizationService::getRolePermissions('viewer', $team->id);

        $this->assertCount(1, $permissions);
        $this->assertEquals('dashboard.view', $permissions->first()->name);
    }

    #[Test]
    public function get_role_permissions_returns_empty_for_nonexistent_role(): void
    {
        $permissions = AuthorizationService::getRolePermissions('nonexistent-role', 999);

        $this->assertCount(0, $permissions);
    }

    // --- getTeamRoles() ---

    #[Test]
    public function get_team_roles_returns_all_roles_for_team(): void
    {
        [$user, $team] = $this->makeTeamWithUser();
        Role::create(['team_id' => $team->id, 'name' => 'role-a', 'display_name' => 'A']);
        Role::create(['team_id' => $team->id, 'name' => 'role-b', 'display_name' => 'B']);

        $roles = AuthorizationService::getTeamRoles($team->id);

        $this->assertGreaterThanOrEqual(2, $roles->count());
        $this->assertTrue($roles->pluck('name')->contains('role-a'));
        $this->assertTrue($roles->pluck('name')->contains('role-b'));
    }

    // --- assignUserRole() / removeUserRole() ---

    #[Test]
    public function assign_user_role_attaches_role_to_user(): void
    {
        [$user, $team] = $this->makeTeamWithUser();
        $role = $this->makeRole($team, 'dispatcher');

        AuthorizationService::assignUserRole($user, $role);

        $this->assertTrue($user->roles()->where('roles.id', $role->id)->exists());
    }

    #[Test]
    public function assign_user_role_accepts_role_name_string(): void
    {
        [$user, $team] = $this->makeTeamWithUser();
        Role::create(['team_id' => $team->id, 'name' => 'supervisor', 'display_name' => 'Supervisor']);

        AuthorizationService::assignUserRole($user, 'supervisor');

        $this->assertTrue($user->roles()->where('name', 'supervisor')->exists());
    }

    #[Test]
    public function assign_user_role_returns_false_for_missing_role(): void
    {
        [$user, $team] = $this->makeTeamWithUser();

        $result = AuthorizationService::assignUserRole($user, 'nonexistent-role');

        $this->assertFalse($result);
    }

    #[Test]
    public function remove_user_role_detaches_role_from_user(): void
    {
        [$user, $team] = $this->makeTeamWithUser();
        $role = $this->makeRole($team, 'analyst');
        $user->roles()->attach($role->id);
        $this->assertTrue($user->roles()->where('roles.id', $role->id)->exists());

        AuthorizationService::removeUserRole($user, $role);

        $this->assertFalse($user->fresh()->roles()->where('roles.id', $role->id)->exists());
    }

    #[Test]
    public function remove_user_role_returns_false_for_missing_role(): void
    {
        [$user, $team] = $this->makeTeamWithUser();

        $result = AuthorizationService::removeUserRole($user, 'nonexistent');

        $this->assertFalse($result);
    }

    // --- createDefaultRoles() ---

    #[Test]
    public function create_default_roles_creates_standard_roles(): void
    {
        [$user, $team] = $this->makeTeamWithUser();

        AuthorizationService::createDefaultRoles($team);

        $roleNames = Role::where('team_id', $team->id)->pluck('name');
        $this->assertTrue($roleNames->contains('admin'));
        $this->assertTrue($roleNames->contains('viewer'));
    }

    #[Test]
    public function create_default_roles_is_idempotent(): void
    {
        [$user, $team] = $this->makeTeamWithUser();

        AuthorizationService::createDefaultRoles($team);
        AuthorizationService::createDefaultRoles($team); // second call should not throw or duplicate

        $adminCount = Role::where('team_id', $team->id)->where('name', 'admin')->count();
        $this->assertEquals(1, $adminCount);
    }
}
