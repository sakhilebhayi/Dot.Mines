<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
