<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * AuthorizationService
 *
 * Service for handling role and permission logic
 * Provides convenience methods for authorization checks
 */
class AuthorizationService
{
    /**
     * Check if user can perform an action
     */
    public static function can(?User $user, string $permission, ?int $teamId = null): bool
    {
        if (! $user) {
            return false;
        }

        $teamId = $teamId ?? $user->current_team_id;

        if (! $teamId) {
            return false;
        }

        // Admins can do everything
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->hasPermission($permission);
    }

    /**
     * Get all permissions for a role
     *
     * @return Collection<int, mixed>
     */
    public static function getRolePermissions(Role|string $role, ?int $teamId = null): Collection
    {
        if (is_string($role)) {
            $role = Role::where('name', $role)
                ->when($teamId, fn ($q) => $q->where('team_id', $teamId))
                ->first();
        }

        return $role?->permissions ?? collect();
    }

    /**
     * Get all roles for a team
     *
     * @return Collection<int, Role>
     */
    public static function getTeamRoles(int $teamId): Collection
    {
        return Role::where('team_id', $teamId)->get();
    }

    /**
     * Get all permissions for a team
     *
     * @return Collection<int, Permission>
     */
    public static function getTeamPermissions(int $teamId): Collection
    {
        return Permission::where('team_id', $teamId)->get();
    }

    /**
     * Get permissions grouped by group
     *
     * @return Collection<string, Collection<int, Permission>>
     */
    public static function getPermissionsByGroup(int $teamId): Collection
    {
        return Permission::where('team_id', $teamId)
            ->get()
            ->groupBy('group');
    }

    /**
     * Get role with permissions
     */
    public static function getRoleWithPermissions(int $roleId): mixed
    {
        return Role::with('permissions')->findOrFail($roleId);
    }

    /**
     * Create default roles for a team
     */
    public static function createDefaultRoles(Team $team): void
    {
        $roles = [
            [
                'name' => 'admin',
                'display_name' => 'Administrator',
                'description' => 'Full system access',
            ],
            [
                'name' => 'fleet_manager',
                'display_name' => 'Fleet Manager',
                'description' => 'Can manage machines and view reports',
            ],
            [
                'name' => 'operator',
                'display_name' => 'Operator',
                'description' => 'Can view machines and maps',
            ],
            [
                'name' => 'viewer',
                'display_name' => 'Viewer',
                'description' => 'Read-only access',
            ],
        ];

        foreach ($roles as $roleData) {
            Role::firstOrCreate([
                'team_id' => $team->id,
                'name' => $roleData['name'],
            ], [
                'display_name' => $roleData['display_name'],
                'description' => $roleData['description'],
            ]);
        }
    }

    /**
     * Assign user to role
     */
    public static function assignUserRole(User $user, Role|string $role, ?int $teamId = null): bool
    {
        $teamId = $teamId ?? $user->current_team_id;

        if (is_string($role)) {
            $role = Role::where('team_id', $teamId)
                ->where('name', $role)
                ->first();
        }

        if (! $role) {
            return false;
        }

        $user->roles()->attach($role->id);

        return true;
    }

    /**
     * Remove user from role
     */
    public static function removeUserRole(User $user, Role|string $role, ?int $teamId = null): bool
    {
        $teamId = $teamId ?? $user->current_team_id;

        if (is_string($role)) {
            $role = Role::where('team_id', $teamId)
                ->where('name', $role)
                ->first();
        }

        if (! $role) {
            return false;
        }

        $user->roles()->detach($role->id);

        return true;
    }
}
