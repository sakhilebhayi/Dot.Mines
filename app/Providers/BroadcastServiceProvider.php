<?php

namespace App\Providers;

use App\Models\Geofence;
use App\Models\Machine;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Broadcast::routes() is already registered by bootstrap/app.php's
        // withRouting(channels: ...), which also requires routes/channels.php.
        // Only the private/presence channel authorization callbacks live here.
        $this->requireChannels();
    }

    /**
     * Authenticate access to private channels.
     */
    protected function requireChannels(): void
    {
        Broadcast::channel('user.{id}', function (User $user, int $id) {
            return (int) $user->id === (int) $id;
        });

        /**
         * Team-based channels for fleet monitoring
         */
        Broadcast::channel('team.{teamId}', function (User $user, int $teamId) {
            return static::belongsToTeamId($user, $teamId) ? ['id' => $user->id, 'name' => $user->name] : false;
        });

        /**
         * Machine-specific channels for real-time updates
         */
        Broadcast::channel('machine.{machineId}', function (User $user, int $machineId) {
            // User must be part of the team that owns the machine
            $machine = Machine::find($machineId);

            if (! $machine) {
                return false;
            }

            return static::belongsToTeamId($user, $machine->team_id) ? ['id' => $user->id, 'name' => $user->name] : false;
        });

        /**
         * Geofence-specific channels
         */
        Broadcast::channel('geofence.{geofenceId}', function (User $user, int $geofenceId) {
            $geofence = Geofence::find($geofenceId);

            if (! $geofence) {
                return false;
            }

            return static::belongsToTeamId($user, $geofence->team_id) ? ['id' => $user->id, 'name' => $user->name] : false;
        });

        /**
         * Alert-specific channels
         */
        Broadcast::channel('alerts.team.{teamId}', function (User $user, int $teamId) {
            return static::belongsToTeamId($user, $teamId) ? ['id' => $user->id, 'name' => $user->name] : false;
        });

        /**
         * Global team presence channel for dashboard updates
         */
        Broadcast::channel('team.presence.{teamId}', function (User $user, int $teamId) {
            if (! static::belongsToTeamId($user, $teamId)) {
                return false;
            }

            return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email];
        });
    }

    /**
     * Determine whether the user belongs to the team with the given id.
     *
     * Jetstream's User::belongsToTeam() expects a Team model instance (it
     * reads $team->id / $team->{foreignKey} internally) — passing a raw id
     * silently short-circuits to false on every call, which is what every
     * channel callback in this class did before this helper existed.
     */
    protected static function belongsToTeamId(User $user, int $teamId): bool
    {
        $team = Team::find($teamId);

        return $team !== null && $user->belongsToTeam($team);
    }
}
