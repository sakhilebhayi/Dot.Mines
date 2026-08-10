<?php

namespace Tests\Feature;

use App\Models\Geofence;
use App\Models\Machine;
use App\Models\Team;
use App\Models\User;
use App\Providers\BroadcastServiceProvider;
use App\Services\TeamRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * app/Providers/BroadcastServiceProvider.php defines authorization callbacks
 * for the private channels the real-time fleet events broadcast on
 * (team.{id}, machine.{id}, etc.), but the provider was never registered in
 * bootstrap/providers.php, so none of these callbacks ever ran. This test
 * exercises the actual /broadcasting/auth endpoint to prove tenant isolation
 * -- and, since the security pass in sub-project 4, permission-level
 * authorization -- now holds for those channels.
 */
class BroadcastChannelAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // phpunit.xml forces BROADCAST_CONNECTION=null in the test env so
        // event-broadcasting tests never attempt a real network call — but
        // the null driver's auth() is a no-op that authorizes everything
        // unconditionally. Channel-authorization callbacks are only
        // enforced by a real Pusher-protocol broadcaster (reverb/pusher),
        // so this test class opts back into that for its own requests.
        //
        // Switching the config alone isn't enough: Broadcast::channel()
        // registers callbacks on whichever driver instance is current at
        // call time, and BroadcastServiceProvider::boot() already ran
        // against the "null" driver during app bootstrap (before this
        // setUp() runs). Re-running its registration now, after switching
        // the default, registers the same callbacks on a fresh "reverb"
        // driver instance instead.
        config(['broadcasting.default' => 'reverb']);
        (new BroadcastServiceProvider($this->app))->boot();
    }

    private function authRequest(User $user, string $channelName): TestResponse
    {
        return $this->actingAs($user)->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => $channelName,
        ]);
    }

    /**
     * Team member with no role/permissions attached -- the baseline for
     * every "belongs to the team but shouldn't have this specific
     * permission" test below.
     */
    private function bareMember(Team $team): User
    {
        $member = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($member->id);

        return $member;
    }

    // -- team.{teamId} / machine.{machineId}: gated on track_machines --

    public function test_team_member_with_track_machines_permission_can_authorize_team_channel(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $member = $this->bareMember($team);
        TeamRoleProvisioner::assignRole($member, $team, 'operator');

        $this->authRequest($member, "private-team.{$team->id}")
            ->assertOk();
    }

    /**
     * TeamRoleProvisioner's "viewer" role is deliberately given
     * view_machines/view_live_map but not track_machines -- a viewer can
     * see the machine list but must not receive a live GPS feed over the
     * WebSocket. Before this sub-project, team membership alone was
     * sufficient and this would have passed.
     */
    public function test_team_member_without_track_machines_permission_cannot_authorize_team_channel(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $member = $this->bareMember($team);
        TeamRoleProvisioner::assignRole($member, $team, 'viewer');

        $this->authRequest($member, "private-team.{$team->id}")
            ->assertForbidden();
    }

    public function test_non_member_cannot_authorize_team_channel(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $outsider = User::factory()->create();

        $this->authRequest($outsider, "private-team.{$team->id}")
            ->assertForbidden();
    }

    public function test_team_member_with_track_machines_permission_can_authorize_machine_channel(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $member = $this->bareMember($team);
        TeamRoleProvisioner::assignRole($member, $team, 'operator');
        $machine = Machine::factory()->create(['team_id' => $team->id]);

        $this->authRequest($member, "private-machine.{$machine->id}")
            ->assertOk();
    }

    public function test_team_member_without_track_machines_permission_cannot_authorize_machine_channel(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $member = $this->bareMember($team);
        TeamRoleProvisioner::assignRole($member, $team, 'viewer');
        $machine = Machine::factory()->create(['team_id' => $team->id]);

        $this->authRequest($member, "private-machine.{$machine->id}")
            ->assertForbidden();
    }

    public function test_user_cannot_authorize_machine_channel_for_another_teams_machine(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $member = $this->bareMember($team);
        TeamRoleProvisioner::assignRole($member, $team, 'operator');

        $otherOwner = User::factory()->create();
        $otherTeam = Team::factory()->create(['user_id' => $otherOwner->id]);
        $machine = Machine::factory()->create(['team_id' => $otherTeam->id]);

        $this->authRequest($member, "private-machine.{$machine->id}")
            ->assertForbidden();
    }

    // -- geofence.{geofenceId}: gated on view_geofences --

    public function test_team_member_with_view_geofences_permission_can_authorize_geofence_channel(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $member = $this->bareMember($team);
        TeamRoleProvisioner::assignRole($member, $team, 'viewer');
        $geofence = Geofence::factory()->create(['team_id' => $team->id]);

        $this->authRequest($member, "private-geofence.{$geofence->id}")
            ->assertOk();
    }

    public function test_team_member_without_view_geofences_permission_cannot_authorize_geofence_channel(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $member = $this->bareMember($team);
        $geofence = Geofence::factory()->create(['team_id' => $team->id]);

        $this->authRequest($member, "private-geofence.{$geofence->id}")
            ->assertForbidden();
    }

    public function test_user_cannot_authorize_geofence_channel_for_another_teams_geofence(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $member = $this->bareMember($team);
        TeamRoleProvisioner::assignRole($member, $team, 'viewer');

        $otherOwner = User::factory()->create();
        $otherTeam = Team::factory()->create(['user_id' => $otherOwner->id]);
        $geofence = Geofence::factory()->create(['team_id' => $otherTeam->id]);

        $this->authRequest($member, "private-geofence.{$geofence->id}")
            ->assertForbidden();
    }

    // -- alerts.team.{teamId} (AlertTriggered): gated on view_alerts --

    public function test_team_member_with_view_alerts_permission_can_authorize_alerts_channel(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $member = $this->bareMember($team);
        TeamRoleProvisioner::assignRole($member, $team, 'viewer');

        $this->authRequest($member, "private-alerts.team.{$team->id}")
            ->assertOk();
    }

    public function test_team_member_without_view_alerts_permission_cannot_authorize_alerts_channel(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $member = $this->bareMember($team);

        $this->authRequest($member, "private-alerts.team.{$team->id}")
            ->assertForbidden();
    }

    public function test_non_member_cannot_authorize_alerts_channel(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $outsider = User::factory()->create();

        $this->authRequest($outsider, "private-alerts.team.{$team->id}")
            ->assertForbidden();
    }

    // -- team.{teamId}.alerts (MaintenanceAlertTriggered): gated on view_alerts --

    public function test_team_member_with_view_alerts_permission_can_authorize_maintenance_alerts_channel(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $member = $this->bareMember($team);
        TeamRoleProvisioner::assignRole($member, $team, 'viewer');

        $this->authRequest($member, "private-team.{$team->id}.alerts")
            ->assertOk();
    }

    public function test_team_member_without_view_alerts_permission_cannot_authorize_maintenance_alerts_channel(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $member = $this->bareMember($team);

        $this->authRequest($member, "private-team.{$team->id}.alerts")
            ->assertForbidden();
    }

    public function test_non_member_cannot_authorize_maintenance_alerts_channel(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $outsider = User::factory()->create();

        $this->authRequest($outsider, "private-team.{$team->id}.alerts")
            ->assertForbidden();
    }

    // -- team.{teamId}.compliance (ComplianceViolationDetected): gated on view_alerts --

    public function test_team_member_with_view_alerts_permission_can_authorize_compliance_channel(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $member = $this->bareMember($team);
        TeamRoleProvisioner::assignRole($member, $team, 'viewer');

        $this->authRequest($member, "private-team.{$team->id}.compliance")
            ->assertOk();
    }

    public function test_team_member_without_view_alerts_permission_cannot_authorize_compliance_channel(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $member = $this->bareMember($team);

        $this->authRequest($member, "private-team.{$team->id}.compliance")
            ->assertForbidden();
    }

    public function test_non_member_cannot_authorize_compliance_channel(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $outsider = User::factory()->create();

        $this->authRequest($outsider, "private-team.{$team->id}.compliance")
            ->assertForbidden();
    }

    // -- team.presence.{teamId}: membership only, no extra permission --

    public function test_team_member_can_authorize_presence_channel(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $member = $this->bareMember($team);

        $this->authRequest($member, "presence-team.presence.{$team->id}")
            ->assertOk();
    }

    public function test_non_member_cannot_authorize_presence_channel(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $outsider = User::factory()->create();

        $this->authRequest($outsider, "presence-team.presence.{$team->id}")
            ->assertForbidden();
    }

    // -- defense in depth: malformed channel identifiers fail closed --

    public function test_non_numeric_team_identifier_fails_closed_rather_than_erroring(): void
    {
        $user = User::factory()->create();

        $this->authRequest($user, 'private-team.not-a-number')
            ->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_authorize_any_private_channel(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);

        $this->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-team.{$team->id}",
        ])->assertForbidden();
    }
}
