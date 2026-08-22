<?php

namespace Tests\Feature;

use App\Models\Machine;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HasTeamFilters' container fallback exists so queue jobs and commands can
 * set a team context -- it must only ever fill in when the authenticated
 * user has NO team, never override a real session team. A refactor pass
 * once turned that precedence into "container always wins when bound",
 * which would leak another tenant's rows into an authenticated request
 * whose worker process had a stale binding. These tests freeze the
 * precedence.
 */
class TeamScopeContainerFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_real_session_team_beats_a_bound_container_team(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $otherTeam = Team::factory()->create();

        $mine = Machine::factory()->create(['team_id' => $user->currentTeam->id, 'name' => 'MY-TRUCK']);
        Machine::factory()->create(['team_id' => $otherTeam->id, 'name' => 'FOREIGN-TRUCK']);

        $this->actingAs($user);
        app()->instance('current_team_id', $otherTeam->id);

        try {
            $names = Machine::query()->pluck('name');
        } finally {
            app()->forgetInstance('current_team_id');
        }

        $this->assertTrue($names->contains('MY-TRUCK'));
        $this->assertFalse($names->contains('FOREIGN-TRUCK'), 'A stale container binding must never override the session team.');
        $this->assertSame($mine->id, Machine::query()->first()?->id);
    }

    public function test_container_team_applies_when_there_is_no_authenticated_team(): void
    {
        $team = Team::factory()->create();
        $other = Team::factory()->create();
        Machine::factory()->create(['team_id' => $team->id, 'name' => 'JOB-TRUCK']);
        Machine::factory()->create(['team_id' => $other->id, 'name' => 'OTHER-TRUCK']);

        app()->instance('current_team_id', $team->id);

        try {
            $names = Machine::query()->pluck('name');
        } finally {
            app()->forgetInstance('current_team_id');
        }

        $this->assertTrue($names->contains('JOB-TRUCK'));
        $this->assertFalse($names->contains('OTHER-TRUCK'));
    }
}
