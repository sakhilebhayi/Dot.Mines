<?php

namespace Tests\Feature;

use App\Models\AIInsight;
use App\Models\AIRecommendation;
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

    /**
     * The AI section used to receive permanently-empty collections (a
     * "placeholder for future AI integration"), so ~150 lines of blade
     * could never render. It now surfaces the real dispatch intelligence
     * DispatchAdvisorAgent derives from geofence queue activity.
     */
    public function test_geofences_page_shows_real_pending_dispatch_recommendations(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        AIRecommendation::factory()->create([
            'team_id' => $team->id,
            'category' => 'dispatch',
            'status' => 'pending',
            'priority' => 'high',
            'title' => 'Shift two trucks from North Pit to Crusher 1',
        ]);

        // Must NOT appear: wrong category, wrong team.
        AIRecommendation::factory()->create([
            'team_id' => $team->id,
            'category' => 'maintenance',
            'status' => 'pending',
            'title' => 'Grease the swing bearing',
        ]);
        AIRecommendation::factory()->create([
            'category' => 'dispatch',
            'status' => 'pending',
            'title' => 'Other tenant queue rebalance',
        ]);

        $response = $this->actingAs($user)->get('/geofences');

        $response->assertOk();
        $response->assertSee('Shift two trucks from North Pit to Crusher 1');
        $response->assertDontSee('Grease the swing bearing');
        $response->assertDontSee('Other tenant queue rebalance');
    }

    public function test_geofences_page_shows_current_dispatch_insights_and_hides_expired_ones(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        AIInsight::factory()->create([
            'team_id' => $team->id,
            'category' => 'dispatch',
            'severity' => 'warning',
            'title' => 'Queue imbalance between crusher geofences',
            'valid_until' => now()->addDay(),
        ]);
        AIInsight::factory()->create([
            'team_id' => $team->id,
            'category' => 'dispatch',
            'title' => 'Stale queue insight from last week',
            'valid_until' => now()->subDay(),
        ]);

        $response = $this->actingAs($user)->get('/geofences');

        $response->assertOk();
        $response->assertSee('Queue imbalance between crusher geofences');
        $response->assertDontSee('Stale queue insight from last week');
    }
}
