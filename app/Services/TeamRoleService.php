<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TeamRoleService
{
    /**
     * All permissions available in the application.
     *
     * @return array<int, array<string, string>>
     */
    public static function allPermissions(): array
    {
        return [
            ['name' => 'view_dashboard', 'display_name' => 'View Dashboard', 'group' => 'dashboard', 'description' => 'View main dashboard and metrics'],

            ['name' => 'view_machines', 'display_name' => 'View Machines', 'group' => 'machines', 'description' => 'View fleet machines'],
            ['name' => 'create_machines', 'display_name' => 'Create Machines', 'group' => 'machines', 'description' => 'Add new machines to fleet'],
            ['name' => 'update_machines', 'display_name' => 'Update Machines', 'group' => 'machines', 'description' => 'Update machine information'],
            ['name' => 'delete_machines', 'display_name' => 'Delete Machines', 'group' => 'machines', 'description' => 'Remove machines from fleet'],
            ['name' => 'track_machines', 'display_name' => 'Track Machines', 'group' => 'machines', 'description' => 'Track real-time machine location'],
            ['name' => 'view_metrics', 'display_name' => 'View Metrics', 'group' => 'machines', 'description' => 'View machine sensor metrics'],

            ['name' => 'view_live_map', 'display_name' => 'View Live Map', 'group' => 'map', 'description' => 'View real-time machine locations'],

            ['name' => 'view_geofences', 'display_name' => 'View Geofences', 'group' => 'geofences', 'description' => 'View pit/geofence areas'],
            ['name' => 'create_geofences', 'display_name' => 'Create Geofences', 'group' => 'geofences', 'description' => 'Create new pit areas'],
            ['name' => 'update_geofences', 'display_name' => 'Update Geofences', 'group' => 'geofences', 'description' => 'Update pit information'],
            ['name' => 'delete_geofences', 'display_name' => 'Delete Geofences', 'group' => 'geofences', 'description' => 'Remove pit areas'],

            ['name' => 'view_reports', 'display_name' => 'View Reports', 'group' => 'reports', 'description' => 'View generated reports'],
            ['name' => 'create_reports', 'display_name' => 'Create Reports', 'group' => 'reports', 'description' => 'Generate new reports'],
            ['name' => 'update_reports', 'display_name' => 'Update Reports', 'group' => 'reports', 'description' => 'Update report settings'],
            ['name' => 'delete_reports', 'display_name' => 'Delete Reports', 'group' => 'reports', 'description' => 'Remove reports'],

            ['name' => 'view_integrations', 'display_name' => 'View Integrations', 'group' => 'integrations', 'description' => 'View API integrations'],
            ['name' => 'manage_integrations', 'display_name' => 'Manage Integrations', 'group' => 'integrations', 'description' => 'Add/edit API integrations'],
            ['name' => 'sync_integrations', 'display_name' => 'Sync Integrations', 'group' => 'integrations', 'description' => 'Trigger integration data sync'],

            ['name' => 'view_alerts', 'display_name' => 'View Alerts', 'group' => 'alerts', 'description' => 'View system alerts'],
            ['name' => 'create_alerts', 'display_name' => 'Create Alerts', 'group' => 'alerts', 'description' => 'Create manual alerts'],
            ['name' => 'update_alerts', 'display_name' => 'Update Alerts', 'group' => 'alerts', 'description' => 'Update alert settings'],
            ['name' => 'delete_alerts', 'display_name' => 'Delete Alerts', 'group' => 'alerts', 'description' => 'Remove alerts'],
            ['name' => 'acknowledge_alerts', 'display_name' => 'Acknowledge Alerts', 'group' => 'alerts', 'description' => 'Mark alerts as acknowledged'],
            ['name' => 'resolve_alerts', 'display_name' => 'Resolve Alerts', 'group' => 'alerts', 'description' => 'Mark alerts as resolved'],

            ['name' => 'view_settings', 'display_name' => 'View Settings', 'group' => 'settings', 'description' => 'View team settings'],
            ['name' => 'manage_settings', 'display_name' => 'Manage Settings', 'group' => 'settings', 'description' => 'Modify team settings'],
            ['name' => 'manage_users', 'display_name' => 'Manage Users', 'group' => 'settings', 'description' => 'Add/remove team members'],
            ['name' => 'manage_roles', 'display_name' => 'Manage Roles', 'group' => 'settings', 'description' => 'Assign roles to users'],
        ];
    }

    /**
     * All role definitions with their permission sets.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function allRoles(): array
    {
        $allPermissionNames = array_column(static::allPermissions(), 'name');

        return [
            'admin' => [
                'display_name' => 'Administrator',
                'description' => 'Full system access',
                'permissions' => $allPermissionNames,
            ],
            'fleet_manager' => [
                'display_name' => 'Fleet Manager',
                'description' => 'Can manage machines and view reports',
                'permissions' => [
                    'view_dashboard',
                    'view_machines', 'create_machines', 'update_machines', 'track_machines', 'view_metrics',
                    'view_live_map',
                    'view_geofences', 'create_geofences', 'update_geofences',
                    'view_reports', 'create_reports',
                    'view_integrations',
                    'view_alerts', 'acknowledge_alerts', 'resolve_alerts',
                    'view_settings',
                ],
            ],
            'operator' => [
                'display_name' => 'Operator',
                'description' => 'Can view machines and maps',
                'permissions' => [
                    'view_dashboard',
                    'view_machines', 'track_machines', 'view_metrics',
                    'view_live_map',
                    'view_geofences',
                    'view_alerts', 'acknowledge_alerts',
                ],
            ],
            'viewer' => [
                'display_name' => 'Viewer',
                'description' => 'Read-only access',
                'permissions' => [
                    'view_dashboard',
                    'view_machines', 'view_metrics',
                    'view_live_map',
                    'view_geofences',
                    'view_reports',
                    'view_alerts',
                ],
            ],
        ];
    }

    /**
     * Provision all roles and permissions for a team.
     * Safe to call multiple times — uses upsert/firstOrCreate to avoid duplication.
     * Optionally assigns the admin role to the given owner.
     */
    public static function provisionTeam(Team $team, ?User $owner = null): void
    {
        DB::transaction(function () use ($team, $owner): void {
            // 1 — Ensure all permissions exist for this team
            foreach (static::allPermissions() as $permData) {
                Permission::firstOrCreate(
                    ['team_id' => $team->id, 'name' => $permData['name']],
                    [
                        'display_name' => $permData['display_name'],
                        'group' => $permData['group'],
                        'description' => $permData['description'],
                    ]
                );
            }

            // 2 — Ensure all roles exist and have the correct permissions
            foreach (static::allRoles() as $roleName => $roleData) {
                $role = Role::firstOrCreate(
                    ['team_id' => $team->id, 'name' => $roleName],
                    [
                        'display_name' => $roleData['display_name'],
                        'description' => $roleData['description'],
                    ]
                );

                $permissionIds = Permission::where('team_id', $team->id)
                    ->whereIn('name', $roleData['permissions'])
                    ->pluck('id')
                    ->toArray();

                $role->permissions()->sync($permissionIds);
            }

            // 3 — Assign admin role to the owner if provided
            if ($owner) {
                $adminRole = Role::where('team_id', $team->id)->where('name', 'admin')->first();

                if ($adminRole) {
                    $owner->roles()->syncWithoutDetaching([$adminRole->id]);
                }
            }
        });
    }
}
