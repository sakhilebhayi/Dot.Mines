<?php

namespace Tests\Feature;

use App\Jobs\CheckAIDriftJob;
use App\Jobs\SendNotificationEmailJob;
use App\Models\AIAgent;
use App\Models\AILearningData;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CheckAIDriftJobTest extends TestCase
{
    use RefreshDatabase;

    private int $teamId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->teamId = Team::factory()->create()->id;
    }

    private function makeAgent(float $accuracyScore = 0.85, string $status = 'active'): AIAgent
    {
        return AIAgent::factory()->create([
            'accuracy_score' => $accuracyScore,
            'status' => $status,
            'predictions_made' => 100,
            'successful_predictions' => (int) ($accuracyScore * 100),
        ]);
    }

    private function addLearningData(AIAgent $agent, int $correct, int $total): void
    {
        for ($i = 0; $i < $correct; $i++) {
            AILearningData::create([
                'ai_agent_id' => $agent->id,
                'team_id' => $this->teamId,
                'data_type' => 'prediction',
                'was_accurate' => true,
                'input_data' => ['test' => true],
                'predicted_output' => ['result' => 'yes'],
                'actual_output' => ['result' => 'yes'],
                'accuracy' => 1.0,
            ]);
        }

        $incorrect = $total - $correct;
        for ($i = 0; $i < $incorrect; $i++) {
            AILearningData::create([
                'ai_agent_id' => $agent->id,
                'team_id' => $this->teamId,
                'data_type' => 'prediction',
                'was_accurate' => false,
                'input_data' => ['test' => true],
                'predicted_output' => ['result' => 'yes'],
                'actual_output' => ['result' => 'no'],
                'accuracy' => 0.0,
            ]);
        }
    }

    #[Test]
    public function healthy_agent_stays_active_after_analysis(): void
    {
        Queue::fake();

        $agent = $this->makeAgent(0.85);
        $this->addLearningData($agent, 80, 100); // 80% — above 70% warn threshold

        (new CheckAIDriftJob)->handle();

        Queue::assertNotPushed(SendNotificationEmailJob::class);
        $this->assertEquals('active', $agent->fresh()->status);
        $this->assertEqualsWithDelta(0.80, $agent->fresh()->accuracy_score, 0.01);
    }

    #[Test]
    public function agent_below_warning_threshold_stays_active(): void
    {
        Queue::fake();

        $agent = $this->makeAgent(0.85);
        $this->addLearningData($agent, 65, 100); // 65% — below 70% threshold

        (new CheckAIDriftJob)->handle();

        $this->assertEquals('active', $agent->fresh()->status);
        $this->assertEqualsWithDelta(0.65, $agent->fresh()->accuracy_score, 0.01);
    }

    #[Test]
    public function agent_below_disable_threshold_is_set_to_degraded(): void
    {
        Queue::fake();

        $agent = $this->makeAgent(0.85);
        $this->addLearningData($agent, 45, 100); // 45% — below 50% disable threshold

        (new CheckAIDriftJob)->handle();

        $this->assertEquals('degraded', $agent->fresh()->status);
        $this->assertEqualsWithDelta(0.45, $agent->fresh()->accuracy_score, 0.01);
    }

    #[Test]
    public function agent_with_insufficient_data_points_is_skipped(): void
    {
        Queue::fake();

        $agent = $this->makeAgent(0.90);
        $this->addLearningData($agent, 2, 5); // Only 5 points — below MIN_DATA_POINTS=10

        $originalScore = $agent->accuracy_score;

        (new CheckAIDriftJob)->handle();

        $this->assertEqualsWithDelta($originalScore, $agent->fresh()->accuracy_score, 0.01);
        $this->assertEquals('active', $agent->fresh()->status);
    }

    #[Test]
    public function inactive_agents_are_not_analysed(): void
    {
        Queue::fake();

        $agent = $this->makeAgent(0.90, 'inactive');
        $this->addLearningData($agent, 10, 100);

        $originalScore = $agent->accuracy_score;

        (new CheckAIDriftJob)->handle();

        $this->assertEqualsWithDelta($originalScore, $agent->fresh()->accuracy_score, 0.01);
        $this->assertEquals('inactive', $agent->fresh()->status);
    }

    #[Test]
    public function accuracy_score_is_updated_after_analysis(): void
    {
        Queue::fake();

        $agent = $this->makeAgent(0.50);
        $this->addLearningData($agent, 75, 100); // 75% in last 30 days

        (new CheckAIDriftJob)->handle();

        $this->assertEqualsWithDelta(0.75, $agent->fresh()->accuracy_score, 0.01);
        $this->assertEquals(100, $agent->fresh()->predictions_made);
        $this->assertEquals(75, $agent->fresh()->successful_predictions);
    }

    #[Test]
    public function only_recent_records_within_window_are_counted(): void
    {
        Queue::fake();

        $agent = $this->makeAgent(0.80);

        // 20 current records: 15 correct (within 30-day window)
        $this->addLearningData($agent, 15, 20);

        // 10 old records from 60 days ago — should be ignored
        for ($i = 0; $i < 10; $i++) {
            $record = AILearningData::create([
                'ai_agent_id' => $agent->id,
                'team_id' => $this->teamId,
                'data_type' => 'prediction',
                'was_accurate' => false, // all wrong — would drag accuracy to ~25%
                'input_data' => ['test' => true],
                'predicted_output' => ['result' => 'yes'],
                'actual_output' => ['result' => 'no'],
                'accuracy' => 0.0,
            ]);
            DB::table('ai_learning_data')
                ->where('id', $record->id)
                ->update(['created_at' => now()->subDays(60)]);
        }

        (new CheckAIDriftJob)->handle();

        // 15/20 current = 0.75 — old records excluded
        $this->assertEqualsWithDelta(0.75, $agent->fresh()->accuracy_score, 0.01);
    }

    #[Test]
    public function multiple_agents_are_handled_independently(): void
    {
        Queue::fake();

        $healthy = $this->makeAgent(0.85);
        $this->addLearningData($healthy, 80, 100);

        $disabled = $this->makeAgent(0.85);
        $this->addLearningData($disabled, 40, 100); // below disable threshold

        (new CheckAIDriftJob)->handle();

        $this->assertEquals('active', $healthy->fresh()->status);
        $this->assertEquals('degraded', $disabled->fresh()->status);
    }
}
