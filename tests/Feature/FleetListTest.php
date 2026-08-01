<?php

namespace Tests\Feature;

use App\Models\Machine;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FleetListTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_away_from_fleet_page()
    {
        $response = $this->get('/fleet');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_the_fleet_list_with_their_own_machines()
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $machine = Machine::factory()->create([
            'team_id' => $team->id,
            'name' => 'Test Haul Truck 01',
        ]);

        $response = $this->actingAs($user)->get('/fleet');

        $response->assertOk();
        $response->assertSee('Test Haul Truck 01');
    }

    public function test_fleet_list_does_not_leak_another_teams_machines()
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $otherTeam = Team::factory()->create();
        Machine::factory()->create([
            'team_id' => $otherTeam->id,
            'name' => 'Other Team Excavator',
        ]);

        $response = $this->actingAs($user)->get('/fleet');

        $response->assertOk();
        $response->assertDontSee('Other Team Excavator');
    }
}
