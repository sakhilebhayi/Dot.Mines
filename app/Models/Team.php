<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
 * @property string|null $timezone
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
    protected function casts(): array
    {
        return [
            'personal_team' => 'boolean',
        ];
    }

    /**
     * Get the roles for this team.
     */
    public function roles()
    {
        return $this->hasMany(Role::class);
    }

    /**
     * Get the owner of the team.
     *
     * @return BelongsTo<User>
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the permissions for this team.
     */
    public function permissions()
    {
        return $this->hasMany(Permission::class);
    }

    /**
     * Get the machines for this team.
     */
    public function machines()
    {
        return $this->hasMany(Machine::class);
    }

    /**
     * Get the geofences for this team.
     */
    public function geofences()
    {
        return $this->hasMany(Geofence::class);
    }

    /**
     * Get the alerts for this team.
     */
    public function alerts()
    {
        return $this->hasMany(Alert::class);
    }

    /**
     * Get the integrations for this team.
     */
    public function integrations()
    {
        return $this->hasMany(Integration::class);
    }
}
