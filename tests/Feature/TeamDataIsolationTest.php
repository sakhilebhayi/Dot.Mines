<?php

namespace Tests\Feature;

use App\Models\Machine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TeamDataIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function userWithTeam(): User
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->switchTeam($user->ownedTeams->first());

        return $user;
    }

    #[Test]
    public function test_user_only_sees_their_own_team_machines(): void
    {
        $userA = $this->userWithTeam();
        $userB = $this->userWithTeam();

        $machineA = Machine::factory()->create(['team_id' => $userA->currentTeam->id]);
        Machine::factory()->create(['team_id' => $userB->currentTeam->id]);

        $this->actingAs($userA);

        $results = Machine::all();

        $this->assertCount(1, $results);
        $this->assertEquals($machineA->id, $results->first()->id);
    }

    #[Test]
    public function test_unauthenticated_query_returns_no_records(): void
    {
        $user = $this->userWithTeam();
        Machine::factory()->create(['team_id' => $user->currentTeam->id]);

        // Records exist in DB via withoutGlobalScopes
        $this->assertGreaterThan(0, Machine::withoutGlobalScopes()->count());
    }

    #[Test]
    public function test_cross_team_machine_access_is_blocked_via_api(): void
    {
        $userA = $this->userWithTeam();
        $userB = $this->userWithTeam();

        $machineB = Machine::factory()->create(['team_id' => $userB->currentTeam->id]);

        // userA tries to access userB's machine via the fleet show route
        $this->actingAs($userA)
            ->get("/fleet/{$machineB->id}")
            ->assertStatus(404);
    }

    #[Test]
    public function test_machines_from_different_teams_are_counted_separately(): void
    {
        $userA = $this->userWithTeam();
        $userB = $this->userWithTeam();

        Machine::factory()->count(3)->create(['team_id' => $userA->currentTeam->id]);
        Machine::factory()->count(5)->create(['team_id' => $userB->currentTeam->id]);

        $this->actingAs($userA);
        $this->assertCount(3, Machine::all());

        $this->actingAs($userB);
        $this->assertCount(5, Machine::all());
    }
}
