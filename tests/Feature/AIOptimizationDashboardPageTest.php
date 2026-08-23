<?php

namespace Tests\Feature;

use App\Livewire\AIOptimizationDashboard;
use App\Models\AIInsight;
use App\Models\AIRecommendation;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The /ai-optimization page had no feature test coverage through the real
 * HTTP route before this file (AIOptimizationDashboardApprovalTest exercises
 * the Livewire component's mutation logic in isolation). Added while
 * re-theming resources/views/livewire/ai-optimization-dashboard.blade.php
 * off its light-mode-base + dark:-override pairs.
 */
class AIOptimizationDashboardPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_away_from_ai_optimization(): void
    {
        $response = $this->get('/ai-optimization');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_with_a_team_can_view_ai_optimization(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $response = $this->actingAs($user)->get('/ai-optimization');

        $response->assertOk();
        $response->assertSee('AI Optimization Dashboard');
    }

    public function test_recommendation_action_steps_render_from_impact_analysis(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        AIRecommendation::factory()->create([
            'team_id' => $team->id,
            'title' => 'Grease the swing bearing',
            'status' => 'pending',
            // The agents publish step lists under impact_analysis; the blade
            // once read a phantom ->actionable_steps attribute, so this
            // section had never rendered for anyone.
            'impact_analysis' => ['recommended_actions' => ['Order the bearing kit', 'Book a 4h window']],
        ]);

        Livewire::actingAs($user)
            ->test(AIOptimizationDashboard::class)
            ->set('activeTab', 'recommendations')
            ->assertSee('Grease the swing bearing')
            ->assertSee('Action Steps:')
            ->assertSee('Order the bearing kit')
            ->assertSee('Book a 4h window');
    }

    public function test_insight_cards_show_the_description_text(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        AIInsight::factory()->create([
            'team_id' => $team->id,
            'title' => 'Fleet utilisation trend',
            // description is the real content column; the blade once read a
            // phantom ->insight attribute and rendered every card body empty.
            'description' => 'Utilisation rose 12% week over week.',
            'is_read' => false,
        ]);

        Livewire::actingAs($user)
            ->test(AIOptimizationDashboard::class)
            ->set('activeTab', 'insights')
            ->assertSee('Fleet utilisation trend')
            ->assertSee('Utilisation rose 12% week over week.');
    }
}
