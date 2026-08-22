<?php

namespace Tests\Feature;

use App\Models\Machine;
use App\Models\MineArea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hybrid Slice 3: private-channel tenancy (brief §9, §13). Every broadcast
 * rides team.{id} or machine.{id}; membership in the owning team is the
 * only key, and it is derived from the session -- these tests are the
 * cross-tenant fence for the realtime layer.
 */
class BroadcastChannelAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The pusher broadcaster signs channel auth locally -- no network.
        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher.key' => 'test-key',
            'broadcasting.connections.pusher.secret' => 'test-secret',
            'broadcasting.connections.pusher.app_id' => '1',
        ]);

        // Channel closures register on the BOOT-TIME default broadcaster
        // (null in phpunit); the pusher driver resolved above starts with
        // zero channels, so the routes file must be registered onto it.
        require base_path('routes/channels.php');
    }

    private function authorize(User $user, string $channel)
    {
        return $this->actingAs($user)->post('/broadcasting/auth', [
            'channel_name' => $channel,
            'socket_id' => '123.456',
        ]);
    }

    public function test_team_members_can_join_their_team_channel(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->authorize($user, 'private-team.'.$user->currentTeam->id)->assertOk();
    }

    public function test_outsiders_are_refused_the_team_channel(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $stranger = User::factory()->withPersonalTeam()->create();

        $this->authorize($stranger, 'private-team.'.$user->currentTeam->id)->assertForbidden();
    }

    public function test_machine_channel_follows_the_owning_teams_membership(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $stranger = User::factory()->withPersonalTeam()->create();
        $area = MineArea::factory()->create(['team_id' => $user->currentTeam->id]);
        $machine = Machine::factory()->create([
            'team_id' => $user->currentTeam->id,
            'mine_area_id' => $area->id,
        ]);

        $this->authorize($user, 'private-machine.'.$machine->id)->assertOk();
        $this->authorize($stranger, 'private-machine.'.$machine->id)->assertForbidden();
    }

    public function test_missing_entities_authorize_nobody(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->authorize($user, 'private-team.999999')->assertForbidden();
        $this->authorize($user, 'private-machine.999999')->assertForbidden();
    }

    public function test_guests_cannot_authorize_any_private_channel(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->post('/broadcasting/auth', [
            'channel_name' => 'private-team.'.$user->currentTeam->id,
            'socket_id' => '123.456',
        ])->assertForbidden();
    }
}
