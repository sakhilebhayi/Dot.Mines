<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The /ai-analytics page had no feature test coverage before this file, so
 * nothing verified the Blade view actually rendered. Added while re-theming
 * resources/views/livewire/ai-analytics.blade.php -- which also fixed a
 * `dark:to-gray-750` class (750 isn't a real Tailwind shade, so it silently
 * generated no CSS and the agent-performance cards lost half their intended
 * gradient) -- to confirm the page still compiles and renders.
 */
class AIAnalyticsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_away_from_ai_analytics(): void
    {
        $response = $this->get('/ai-analytics');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_with_a_team_can_view_ai_analytics(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $response = $this->actingAs($user)->get('/ai-analytics');

        $response->assertOk();
        $response->assertSee('AI Analytics');
    }
}
