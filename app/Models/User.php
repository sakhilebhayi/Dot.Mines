<?php

namespace App\Models;

use App\Notifications\VerifyEmailNotification;
use Carbon\Carbon;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
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
 * @property-read Team|null $currentTeam
 * @property string|null $profile_photo_path
 * @property bool $two_factor_confirmed
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $two_factor_confirmed_at
 * @property-read Collection<int, PersonalAccessToken> $tokens
 * @property-read Collection<int, Team> $ownedTeams
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
     * Whether an in-app alert of the given severity should reach this user.
     * 'critical' always passes -- neither the "In-App Alerts" toggle nor
     * quiet hours can suppress it, the same mandatory floor already applied
     * to the notification bell's severity threshold.
     */
    public function wantsInAppAlert(?string $severity = null): bool
    {
        if ($severity === 'critical') {
            return true;
        }

        if (($this->notification_preferences['in_app_alerts'] ?? true) === false) {
            return false;
        }

        return ! $this->isInQuietHours();
    }

    /**
     * Whether an email alert of the given severity should be sent to this
     * user. Same mandatory-critical floor as wantsInAppAlert().
     */
    public function wantsEmailAlert(?string $severity = null): bool
    {
        if ($severity === 'critical') {
            return true;
        }

        if (($this->notification_preferences['email_alerts'] ?? true) === false) {
            return false;
        }

        return ! $this->isInQuietHours();
    }

    /**
     * Whether this user wants "report ready" emails -- the one preference
     * this toggle is named after and, until now, never actually gated.
     */
    public function wantsEmailReports(): bool
    {
        return ($this->notification_preferences['email_reports'] ?? true) !== false;
    }

    /**
     * Quiet hours support an overnight window (e.g. 22:00-08:00), where the
     * end time is numerically before the start time.
     */
    public function isInQuietHours(): bool
    {
        $preferences = $this->notification_preferences ?? [];

        if (($preferences['quiet_hours_enabled'] ?? false) !== true) {
            return false;
        }

        $start = $preferences['quiet_hours_start'] ?? null;
        $end = $preferences['quiet_hours_end'] ?? null;

        if (! $start || ! $end) {
            return false;
        }

        $now = now()->format('H:i');

        return $start <= $end
            ? ($now >= $start && $now < $end)
            : ($now >= $start || $now < $end);
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

    /**
     * This override existed only to add a return type; it silently dropped
     * HasTeams::currentTeam()'s lazy fallback to the user's personal team,
     * so any user reaching here with current_team_id still null (freshly
     * registered, or a route not covered by the ensure_team middleware)
     * got a hard null instead of their own team. Restored to match the
     * trait's real behavior.
     */
    public function currentTeam(): BelongsTo
    {
        if (is_null($this->current_team_id) && $this->id) {
            $this->switchTeam($this->personalTeam());
        }

        return $this->belongsTo(Team::class, 'current_team_id');
    }

    /**
     * Send the email verification notification via the queue, falling back
     * to synchronous delivery when queueing fails -- registration must
     * never 500 because the queue backend is down (happened in production:
     * RedisException "Connection refused" at POST /register; Predis and
     * other drivers throw their own connection exception classes, hence
     * the broad catch on the queue push specifically).
     */
    #[\Override]
    public function sendEmailVerificationNotification(): void
    {
        try {
            $this->notify(new VerifyEmailNotification);
        } catch (\Throwable $e) {
            Log::warning('Queueing verification email failed; sending synchronously', [
                'user_id' => $this->id,
                'error' => $e->getMessage(),
            ]);

            Notification::sendNow($this, new VerifyEmailNotification);
        }
    }
}
