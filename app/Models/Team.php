<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Jetstream\Events\TeamCreated;
use Laravel\Jetstream\Events\TeamDeleted;
use Laravel\Jetstream\Events\TeamUpdated;
use Laravel\Jetstream\Team as JetstreamTeam;

/**
 * Team Model
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string|null $personal_team
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\User $owner
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 */
class Team extends JetstreamTeam
{
    /** @use HasFactory<\Database\Factories\TeamFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'personal_team',
    ];

    /**
     * The event map for the model.
     *
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'created' => TeamCreated::class,
        'updated' => TeamUpdated::class,
        'deleted' => TeamDeleted::class,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'personal_team' => 'boolean',
        ];
    }

    /**
     * Get the roles for this team.
     */
    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\Role, $this> */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    /**
     * Get the owner of the team.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<User>
     */
    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the permissions for this team.
     */
    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\Permission, $this> */
    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class);
    }

    /**
     * Get the machines for this team.
     */
    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\Machine, $this> */
    public function machines(): HasMany
    {
        return $this->hasMany(Machine::class);
    }

    /**
     * Get the geofences for this team.
     */
    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\Geofence, $this> */
    public function geofences(): HasMany
    {
        return $this->hasMany(Geofence::class);
    }

    /**
     * Get the alerts for this team.
     */
    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\Alert, $this> */
    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    /**
     * Get the integrations for this team.
     */
    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\Integration, $this> */
    public function integrations(): HasMany
    {
        return $this->hasMany(Integration::class);
    }

    /**
     * Get the mine areas for this team.
     */
    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\MineArea, $this> */
    public function mineAreas(): HasMany
    {
        return $this->hasMany(MineArea::class);
    }
}
