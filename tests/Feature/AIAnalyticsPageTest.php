<?php

namespace Tests\Feature;

use App\Livewire\AIAnalytics;
use App\Models\AIRecommendation;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

    /**
     * The implementation-rate aggregation used double quotes around the
     * 'implemented' string literal in raw SQL -- SQLite tolerates that, but
     * Postgres reads double quotes as an identifier and 500s the page.
     * This pins the widget computing correctly with real rows present.
     */
    public function test_implementation_rate_computes_from_real_recommendation_statuses(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        AIRecommendation::factory()->create([
            'team_id' => $team->id,
            'status' => 'implemented',
            'created_at' => now()->subDay(),
        ]);
        AIRecommendation::factory()->create([
            'team_id' => $team->id,
            'status' => 'pending',
            'created_at' => now()->subDay(),
        ]);

        $rates = Livewire::actingAs($user)
            ->test(AIAnalytics::class)
            ->viewData('implementationRate');

        $this->assertCount(1, $rates);
        $this->assertSame(2, (int) $rates->first()->total);
        $this->assertSame(1, (int) $rates->first()->implemented);
        $this->assertEqualsWithDelta(50.0, $rates->first()->rate, 0.01);
    }

    /**
     * The four KPI cards were saturated template gradients (blue/green/
     * purple/orange-500) that clashed with the app's token-based design
     * system; they now use the standard ink-soft card pattern.
     */
    public function test_kpi_cards_use_the_design_system_not_template_gradients(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        Livewire::actingAs($user)
            ->test(AIAnalytics::class)
            ->assertDontSeeHtml('from-blue-500 to-blue-600')
            ->assertDontSeeHtml('from-orange-500 to-orange-600')
            ->assertSeeHtml('bg-[var(--ink-soft)] rounded-xl shadow-lg p-6 border border-[var(--line)]');
    }
}
