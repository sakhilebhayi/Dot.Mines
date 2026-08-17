<?php

namespace Tests\Feature;

use App\Models\Geofence;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * /geofences and /geofences/{geofence} had no feature test coverage before
 * this file. Added while re-theming resources/views/livewire/geofence-manager.blade.php
 * and resources/views/livewire/geofence-detail.blade.php to confirm both
 * pages still compile and render.
 */
class GeofencesPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_away_from_geofences(): void
    {
        $response = $this->get('/geofences');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_with_a_team_can_view_the_geofences_list(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $response = $this->actingAs($user)->get('/geofences');

        $response->assertOk();
        $response->assertSee('Geofence Management');
    }

    public function test_authenticated_user_with_a_team_can_view_a_geofence_detail_page(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $geofence = Geofence::factory()->create(['team_id' => $team->id]);

        $response = $this->actingAs($user)->get("/geofences/{$geofence->id}");

        $response->assertOk();
        $response->assertSee($geofence->name);
    }
}
