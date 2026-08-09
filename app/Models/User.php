<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Carbon\Carbon;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Jetstream\HasTeams;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * User Model
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property int|null $current_team_id
 * @property string|null $profile_photo_path
 * @property bool $two_factor_confirmed
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, PersonalAccessToken> $tokens
 * @property-read Collection<int, Team> $ownedTeams
 */
class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasProfilePhoto;
    use HasTeams;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'current_team_id',
        'notification_preferences',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'notification_preferences' => 'array',
        ];
    }

    /**
     * Get roles for current team
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    /**
     * Get permissions through roles for current team
     */
    public function permissions()
    {
        // Return a query builder for permissions granted to this user via their roles.
        // We join through permission_role -> roles -> role_user so callers can further
        // scope by team or permission name.
        return Permission::query()
            ->select('permissions.*')
            ->join('permission_role', 'permissions.id', '=', 'permission_role.permission_id')
            ->join('roles', 'permission_role.role_id', '=', 'roles.id')
            ->join('role_user', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $this->id)
            ->where('roles.team_id', $this->current_team_id);
    }

    /**
     * Check if user has a specific role in current team
     */
    public function hasRole($role): bool
    {
        if (is_string($role)) {
            return $this->roles()
                ->where('team_id', $this->current_team_id)
                ->where('name', $role)
                ->exists();
        }

        return $this->roles()
            ->where('team_id', $this->current_team_id)
            ->whereIn('name', (array) $role)
            ->exists();
    }

    /**
     * Check if user has a specific permission
     */
    public function hasPermission($permission): bool
    {
        if ($this->hasRole('admin')) {
            return true; // Admins have all permissions
        }

        return $this->permissions()
            ->where('permissions.name', $permission)
            ->exists();
    }

    /**
     * Check if user has any of the given permissions
     */
    public function hasAnyPermission($permissions): bool
    {
        if ($this->hasRole('admin')) {
            return true;
        }

        return $this->permissions()
            ->whereIn('permissions.name', (array) $permissions)
            ->exists();
    }

    /**
     * Check if user has all of the given permissions
     */
    public function hasAllPermissions($permissions): bool
    {
        if ($this->hasRole('admin')) {
            return true;
        }

        $count = $this->permissions()
            ->whereIn('permissions.name', (array) $permissions)
            ->count();

        return $count === count((array) $permissions);
    }

    /**
     * Get all roles for user
     */
    public function getAllRoles()
    {
        return $this->roles()
            ->where('team_id', $this->current_team_id)
            ->get();
    }

    /**
     * Assign a role to user
     */
    public function assignRole($role)
    {
        if (is_string($role)) {
            $role = Role::where('team_id', $this->current_team_id)
                ->where('name', $role)
                ->first();
        }

        if (! $role) {
            return false;
        }

        return $this->roles()->sync($role->id, false);
    }

    /**
     * Remove a role from user
     */
    public function removeRole($role)
    {
        if (is_string($role)) {
            $role = Role::where('team_id', $this->current_team_id)
                ->where('name', $role)
                ->first();
        }

        if (! $role) {
            return false;
        }

        return $this->roles()->detach($role->id);
    }

    public function ownedTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'user_id');
    }

    public function currentTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'current_team_id');
    }
}
