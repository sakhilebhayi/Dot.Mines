<?php

namespace Tests\Feature;

use App\Models\AIInsight;
use App\Models\AIRecommendation;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AIModelScopesTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->withPersonalTeam()->create();
        $this->team = $this->user->currentTeam;
        $this->actingAs($this->user);
    }

    public function test_ai_insight_scope_valid_excludes_expired_records(): void
    {
        AIInsight::factory()->create([
            'team_id' => $this->team->id,
            'valid_until' => now()->subDay(),
        ]);
        $valid = AIInsight::factory()->create([
            'team_id' => $this->team->id,
            'valid_until' => now()->addDay(),
        ]);
        $noExpiry = AIInsight::factory()->create([
            'team_id' => $this->team->id,
            'valid_until' => null,
        ]);

        $results = AIInsight::valid()->get();

        $this->assertTrue($results->contains($valid));
        $this->assertTrue($results->contains($noExpiry));
        $this->assertCount(2, $results);
    }

    public function test_ai_recommendation_scope_pending_filters_by_status(): void
    {
        $pending = AIRecommendation::factory()->create([
            'team_id' => $this->team->id,
            'status' => 'pending',
        ]);
        AIRecommendation::factory()->create([
            'team_id' => $this->team->id,
            'status' => 'implemented',
        ]);

        $results = AIRecommendation::pending()->get();

        $this->assertTrue($results->contains($pending));
        $this->assertCount(1, $results);
    }

    public function test_ai_recommendation_scope_high_priority_includes_critical_and_high(): void
    {
        $critical = AIRecommendation::factory()->create([
            'team_id' => $this->team->id,
            'priority' => 'critical',
        ]);
        $high = AIRecommendation::factory()->create([
            'team_id' => $this->team->id,
            'priority' => 'high',
        ]);
        AIRecommendation::factory()->create([
            'team_id' => $this->team->id,
            'priority' => 'medium',
        ]);
        AIRecommendation::factory()->create([
            'team_id' => $this->team->id,
            'priority' => 'low',
        ]);

        $results = AIRecommendation::highPriority()->get();

        $this->assertTrue($results->contains($critical));
        $this->assertTrue($results->contains($high));
        $this->assertCount(2, $results);
    }
}
