<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use App\Services\TeamRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/** The documented token path for integrators: minted once, with the role's limits spelled out. */
class MintIntegrationTokenCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_mints_a_token_and_warns_about_what_the_role_cannot_do(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $owner->update(['current_team_id' => $team->id]);
        TeamRoleProvisioner::assignRole($owner, $team, 'viewer');

        $this->artisan('integration:mint-token', ['email' => $owner->email, 'team' => $team->id, '--name' => 'control-room'])
            ->expectsOutputToContain('Role lacks update_machines')
            ->expectsOutputToContain('Role lacks resolve_alerts')
            ->expectsOutputToContain('Shown once')
            ->assertSuccessful();

        $token = PersonalAccessToken::query()->where('name', 'control-room')->firstOrFail();
        $this->assertSame(['read', 'create', 'update', 'delete'], $token->abilities);
        $this->assertSame($owner->id, $token->tokenable_id);
    }

    public function test_non_members_and_a_different_current_team_are_reported(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $other = Team::factory()->create(['user_id' => $owner->id]);
        $owner->update(['current_team_id' => $other->id]);
        TeamRoleProvisioner::assignRole($owner, $team, 'admin');
        $stranger = User::factory()->create();

        $this->artisan('integration:mint-token', ['email' => $stranger->email, 'team' => $team->id])->expectsOutputToContain('is not a member')->assertFailed();
        $this->artisan('integration:mint-token', ['email' => $owner->email, 'team' => $team->id])->expectsOutputToContain('Current team of')->assertSuccessful();
    }
}
