<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
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
    public static function can($user, $permission, $teamId = null): bool
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
     */
    public static function getRolePermissions($role, $teamId = null): Collection
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
     */
    public static function getTeamRoles($teamId): Collection
    {
        return Role::where('team_id', $teamId)->get();
    }

    /**
     * Get all permissions for a team
     */
    public static function getTeamPermissions($teamId): Collection
    {
        return Permission::where('team_id', $teamId)->get();
    }

    /**
     * Get permissions grouped by group
     */
    public static function getPermissionsByGroup($teamId): Collection
    {
        return Permission::where('team_id', $teamId)
            ->get()
            ->groupBy('group');
    }

    /**
     * Get role with permissions
     */
    public static function getRoleWithPermissions($roleId)
    {
        return Role::with('permissions')->findOrFail($roleId);
    }

    /**
     * Create default roles for a team
     */
    public static function createDefaultRoles($team)
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
    public static function assignUserRole($user, $role, $teamId = null)
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

        return $user->roles()->attach($role->id);
    }

    /**
     * Remove user from role
     */
    public static function removeUserRole($user, $role, $teamId = null)
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

        return $user->roles()->detach($role->id);
    }
}
