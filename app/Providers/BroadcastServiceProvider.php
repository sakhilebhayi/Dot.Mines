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
     *
     * Route-segment parameters below are typed as plain (non-int) so a
     * malformed identifier -- e.g. subscribing to "private-team.not-a-number"
     * -- reaches these callbacks as the literal string instead of PHP
     * throwing a TypeError while coercing it to an `int` parameter (which
     * surfaced as an uncaught 500, not a clean 403). static::toId() is the
     * single place that validates and converts.
     */
    protected function requireChannels(): void
    {
        Broadcast::channel('user.{id}', function (User $user, $id) {
            $id = static::toId($id);

            return $id !== null && $user->id === $id;
        });

        /**
         * Team-based channels for fleet monitoring. Carries
         * MachineLocationUpdated/MachineOffline -- gated on track_machines
         * (not view_machines), matching MachinePolicy::trackLocation(): a
         * role can be allowed to see the machine list without being allowed
         * to receive a live GPS feed. TeamRoleProvisioner's "viewer" role is
         * exactly this case -- view_machines + view_live_map, but
         * deliberately not track_machines.
         */
        Broadcast::channel('team.{teamId}', function (User $user, $teamId) {
            return static::authorizeTeamChannel($user, $teamId, 'track_machines');
        });

        /**
         * Machine-specific channels for real-time updates. Same
         * track_machines gate as team.{teamId} above, since this is the
         * per-machine equivalent of the same live-location feed.
         */
        Broadcast::channel('machine.{machineId}', function (User $user, $machineId) {
            $machineId = static::toId($machineId);
            $machine = $machineId !== null ? Machine::find($machineId) : null;

            if (! $machine) {
                return false;
            }

            return static::authorizeTeamChannel($user, $machine->team_id, 'track_machines');
        });

        /**
         * Geofence-specific channels
         */
        Broadcast::channel('geofence.{geofenceId}', function (User $user, $geofenceId) {
            $geofenceId = static::toId($geofenceId);
            $geofence = $geofenceId !== null ? Geofence::find($geofenceId) : null;

            if (! $geofence) {
                return false;
            }

            return static::authorizeTeamChannel($user, $geofence->team_id, 'view_geofences');
        });

        /**
         * Alert-specific channels
         */
        Broadcast::channel('alerts.team.{teamId}', function (User $user, $teamId) {
            return static::authorizeTeamChannel($user, $teamId, 'view_alerts');
        });

        /**
         * Maintenance alert channel (App\Events\MaintenanceAlertTriggered
         * broadcasts here, not on alerts.team.{teamId} above).
         */
        Broadcast::channel('team.{teamId}.alerts', function (User $user, $teamId) {
            return static::authorizeTeamChannel($user, $teamId, 'view_alerts');
        });

        /**
         * Compliance violation channel (App\Events\ComplianceViolationDetected).
         */
        Broadcast::channel('team.{teamId}.compliance', function (User $user, $teamId) {
            return static::authorizeTeamChannel($user, $teamId, 'view_alerts');
        });

        /**
         * Team notification bell (App\Events\NotificationCreated). Membership
         * only -- every team member has a bell, and the payload is the same
         * in-app notification they can already read via the bell endpoint.
         */
        Broadcast::channel('team.{teamId}.notifications', function (User $user, $teamId) {
            if (! static::belongsToTeamId($user, $teamId)) {
                return false;
            }

            return ['id' => $user->id, 'name' => $user->name];
        });

        /**
         * Global team presence channel for dashboard updates. Membership
         * only -- who's currently online isn't sensitive operational data
         * the way machine/alert/geofence feeds are, so no extra permission
         * gate here.
         */
        Broadcast::channel('team.presence.{teamId}', function (User $user, $teamId) {
            if (! static::belongsToTeamId($user, $teamId)) {
                return false;
            }

            return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email];
        });
    }

    /**
     * Authorize a channel scoped to a team: the user must both belong to
     * the team AND hold the given permission -- team membership alone used
     * to be the only check here, which let any teammate (e.g. a "viewer"
     * without track_machines) receive real-time feeds their role is
     * explicitly meant not to have, even though the equivalent HTTP
     * endpoint (via a Policy) already enforced the permission correctly.
     *
     * @param  int|string  $teamId
     * @return array{id: int, name: string}|false
     */
    protected static function authorizeTeamChannel(User $user, $teamId, string $permission): array|false
    {
        if (! static::belongsToTeamId($user, $teamId)) {
            return false;
        }

        if (! $user->hasPermission($permission)) {
            return false;
        }

        return ['id' => $user->id, 'name' => $user->name];
    }

    /**
     * Determine whether the user belongs to the team with the given id.
     *
     * Jetstream's User::belongsToTeam() expects a Team model instance (it
     * reads $team->id / $team->{foreignKey} internally) — passing a raw id
     * silently short-circuits to false on every call, which is what every
     * channel callback in this class did before this helper existed.
     *
     * @param  int|string  $teamId
     */
    protected static function belongsToTeamId(User $user, $teamId): bool
    {
        $teamId = static::toId($teamId);

        if ($teamId === null) {
            return false;
        }

        $team = Team::find($teamId);

        return $team !== null && $user->belongsToTeam($team);
    }

    /**
     * Validate and convert a raw channel-name segment into a positive
     * integer id, or null if it isn't one. Route-segment placeholders like
     * {teamId} match any non-slash text, not just digits, so a subscriber
     * can send arbitrary strings -- passing those straight to an Eloquent
     * ->find() on an integer primary key key can throw at the database
     * layer (e.g. Postgres rejects non-numeric input for an integer
     * column), and passing them to a typed `int` closure parameter throws
     * a TypeError before the callback body even runs. Both used to
     * surface as an uncaught 500 instead of a clean, fail-closed 403.
     */
    protected static function toId($value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        return null;
    }
}
