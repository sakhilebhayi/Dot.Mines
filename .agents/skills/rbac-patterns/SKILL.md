---
name: rbac-patterns
description: >
  Mines platform role-based access control patterns. Use when: adding a new permission, assigning
  a role in a test, debugging a 403 error, adding a new policy method, understanding which roles
  have which permissions, writing tests for permission-based access, or provisioning a team's
  roles from scratch.
argument-hint: 'Describe the RBAC or permission task you need help with'
---

# RBAC Patterns

## When to Use

- Adding a new permission to the platform
- Assigning roles in tests (adminUser, viewerUser, operatorUser helpers)
- Debugging a 403 when a user should have access
- Writing a new Policy method
- Testing that a role cannot perform a forbidden action

---

## Roles and Their Key Permission Differences

| Role | create_* | update_* | delete_* | acknowledge_alerts |
|---|---|---|---|---|
| `admin` | ✅ | ✅ | ✅ | ✅ |
| `fleet_manager` | ✅ | ✅ | ✅ | ✅ |
| `operator` | ❌ | ❌ | ❌ | ✅ |
| `viewer` | ❌ | ❌ | ❌ | ❌ |

---

## Test Helper — User by Role

```php
// Admin (full permissions)
private function adminUser(): User
{
    $user = User::factory()->withPersonalTeam()->create();
    TeamRoleService::provisionTeam($user->currentTeam, $user);
    return $user;
}

// Viewer (read-only)
private function viewerUser(): User
{
    $user = User::factory()->withPersonalTeam()->create();
    TeamRoleService::provisionTeam($user->currentTeam, $user);
    $user->roles()->detach();
    $role = Role::where('team_id', $user->current_team_id)->where('name', 'viewer')->firstOrFail();
    $user->roles()->attach($role);
    return $user->fresh() ?? $user;
}

// Operator
private function operatorUser(): User
{
    $user = User::factory()->withPersonalTeam()->create();
    TeamRoleService::provisionTeam($user->currentTeam, $user);
    $user->roles()->detach();
    $role = Role::where('team_id', $user->current_team_id)->where('name', 'operator')->firstOrFail();
    $user->roles()->attach($role);
    return $user->fresh() ?? $user;
}
```

---

## Pattern — Adding a New Permission

1. Add to `TeamRoleService::defaultPermissions()`:
```php
['name' => 'export_data', 'display_name' => 'Export Data', 'group' => 'reports', 'description' => 'Export reports to CSV/XLSX'],
```

2. Add to relevant roles in `TeamRoleService::defaultRoles()`:
```php
'admin' => ['permissions' => [..., 'export_data']],
'fleet_manager' => ['permissions' => [..., 'export_data']],
```

3. Re-provision all teams:
```bash
php artisan tinker --execute '
App\Models\Team::all()->each(fn($t) => App\Services\TeamRoleService::syncPermissions($t));
'
```

---

## Pattern — Policy Method

```php
// app/Policies/MyModelPolicy.php
public function export(User $user): bool
{
    return $user->hasPermission('export_data');
}

// In controller:
$this->authorize('export', MyModel::class);
```

---

## Debugging 403

```bash
# Check user permissions
php artisan tinker --execute '
$user = App\Models\User::find(USER_ID);
$user->roles()->where("team_id", $user->current_team_id)->with("permissions")->get()
    ->flatMap->permissions->pluck("name")->sort()->values();
'

# Check required permission name from the policy
grep -n "hasPermission" app/Policies/ModelPolicy.php
```
