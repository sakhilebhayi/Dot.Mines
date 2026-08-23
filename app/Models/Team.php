<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
 * @property string $timezone
 * @property string $currency
 * @property string $language
 * @property string|null $email
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $owner
 * @property-read Collection<int, User> $users
 */
class Team extends JetstreamTeam
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory;

    /**
     * Same relation Jetstream's base Team builds dynamically, restated with
     * the concrete models so both analyzers know members are Users.
     *
     *
     * @phpstan-return BelongsToMany<User,$this> larastan's relation extension
     * does not track using()/as(), so it is given the two-template form.
     *
     * @psalm-return BelongsToMany<User,$this,Membership,'membership'>
     */
    #[\Override]
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, Membership::class)
            ->using(Membership::class)
            ->as('membership')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'personal_team',
        'email',
        'timezone',
        'language',
        'currency',
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
    #[\Override]
    protected function casts(): array
    {
        return [
            'personal_team' => 'boolean',
        ];
    }

    /**
     * Get the roles for this team.
     */
    /** @return HasMany<Role,$this> */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    /**
     * Get the owner of the team.
     *
     * @return BelongsTo<User,$this>
     */
    #[\Override]
    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the permissions for this team.
     */
    /** @return HasMany<Permission,$this> */
    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class);
    }

    /**
     * Get the machines for this team.
     */
    /** @return HasMany<Machine,$this> */
    public function machines(): HasMany
    {
        return $this->hasMany(Machine::class);
    }

    /**
     * Get the geofences for this team.
     */
    /** @return HasMany<Geofence,$this> */
    public function geofences(): HasMany
    {
        return $this->hasMany(Geofence::class);
    }

    /**
     * Get the alerts for this team.
     */
    /** @return HasMany<Alert,$this> */
    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    /**
     * Get the integrations for this team.
     */
    /** @return HasMany<Integration,$this> */
    public function integrations(): HasMany
    {
        return $this->hasMany(Integration::class);
    }
}
