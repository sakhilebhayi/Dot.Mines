<?php

namespace Tests\Feature;

use App\Models\Machine;
use App\Models\Team;
use App\Models\User;
use App\Providers\BroadcastServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * app/Providers/BroadcastServiceProvider.php defines authorization callbacks
 * for the private channels the real-time fleet events broadcast on
 * (team.{id}, machine.{id}, etc.), but the provider was never registered in
 * bootstrap/providers.php, so none of these callbacks ever ran. This test
 * exercises the actual /broadcasting/auth endpoint to prove tenant isolation
 * now holds for those channels.
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

    public function test_team_member_can_authorize_team_channel(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $member = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($member->id);

        $this->authRequest($member, "private-team.{$team->id}")
            ->assertOk();
    }

    public function test_non_member_cannot_authorize_team_channel(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $outsider = User::factory()->create();

        $this->authRequest($outsider, "private-team.{$team->id}")
            ->assertForbidden();
    }

    public function test_team_member_can_authorize_machine_channel_for_their_teams_machine(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $member = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($member->id);
        $machine = Machine::factory()->create(['team_id' => $team->id]);

        $this->authRequest($member, "private-machine.{$machine->id}")
            ->assertOk();
    }

    public function test_user_cannot_authorize_machine_channel_for_another_teams_machine(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);

        $otherOwner = User::factory()->create();
        $otherTeam = Team::factory()->create(['user_id' => $otherOwner->id]);
        $member = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($member->id);

        $machine = Machine::factory()->create(['team_id' => $otherTeam->id]);

        $this->authRequest($member, "private-machine.{$machine->id}")
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
