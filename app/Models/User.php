<?php

namespace App\Models;

use App\Notifications\VerifyEmailNotification;
use Carbon\Carbon;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Notification;
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
 * @property-read Team|null $currentTeam
 */
class User extends Authenticatable implements MustVerifyEmail
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
        ];
    }

    /**
     * Get roles for current team
     */
    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    /**
     * Get permissions through roles for current team
     */
    public function permissions(): Builder
    {
        // Return a query builder for permissions granted to this user via their roles.
        // We join through permission_role -> roles -> role_user so callers can further
        // scope by team or permission name.
        /** @var Builder $query */
        $query = Permission::query()
            ->select('permissions.*')
            ->join('permission_role', 'permissions.id', '=', 'permission_role.permission_id')
            ->join('roles', 'permission_role.role_id', '=', 'roles.id')
            ->join('role_user', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $this->id)
            ->where('roles.team_id', $this->current_team_id)
            ->toBase();

        return $query;
    }

    /**
     * Check if user has a specific role in current team
     *
     * @param  string|array<string>  $role
     */
    public function hasRole(string|array $role): bool
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
    public function hasPermission(string $permission): bool
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
     *
     * @param  string|array<string>  $permissions
     */
    public function hasAnyPermission(string|array $permissions): bool
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
     *
     * @param  string|array<string>  $permissions
     */
    public function hasAllPermissions(string|array $permissions): bool
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
     *
     * @return Collection<int, Role>
     */
    public function getAllRoles(): Collection
    {
        return $this->roles()
            ->where('team_id', $this->current_team_id)
            ->get();
    }

    /**
     * Assign a role to user
     *
     * @return bool|array<mixed>
     */
    public function assignRole(string|Role $role): bool|array
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
    public function removeRole(string|Role $role): bool|int
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

    /**
     * Get the teams owned by the user.
     *
     * @return HasMany<Team>
     */
    /** @return HasMany<Team, $this> */
    public function ownedTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'user_id');
    }

    /**
     * Get the current team of the user's context.
     *
     * @return BelongsTo<Team>
     */
    /** @return BelongsTo<Team, $this> */
    public function currentTeam(): BelongsTo
    {
        if (is_null($this->current_team_id) && $this->id) {
            $this->switchTeam($this->personalTeam());
        }

        return $this->belongsTo(Team::class, 'current_team_id');
    }

    /**
     * Send the email verification notification.
     * Falls back to synchronous delivery if the queue driver is unavailable.
     */
    public function sendEmailVerificationNotification(): void
    {
        try {
            $this->notify(new VerifyEmailNotification);
        } catch (\RedisException $e) {
            Notification::sendNow($this, new VerifyEmailNotification);
        }
    }

    /** @return HasMany<NotificationPreference, $this> */
    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }
}
