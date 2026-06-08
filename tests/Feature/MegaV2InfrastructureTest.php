<?php

namespace Tests\Feature;

use App\Models\AgentPerformanceLog;
use App\Models\AIPredictionOutcome;
use App\Models\DataQualitySnapshot;
use App\Models\KnowledgeGraphEntry;
use App\Models\Team;
use App\Services\AgentReliabilityService;
use App\Services\AI\PredictionAccuracyService;
use App\Services\DataTrustService;
use App\Services\OrganisationalMemoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MegaV2InfrastructureTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────────────────────
    // AIPredictionOutcome — model + PredictionAccuracyService
    // ─────────────────────────────────────────────────────────────────────

    public function test_prediction_outcome_can_be_logged(): void
    {
        $team = Team::factory()->create();
        $service = app(PredictionAccuracyService::class);

        $prediction = $service->logPrediction(
            agentType: 'fuel_predictor',
            predictionType: 'fuel_consumption',
            teamId: $team->id,
            predictedValue: ['litres' => 150],
            confidenceScore: 0.85,
        );

        $this->assertDatabaseHas('ai_prediction_outcomes', [
            'agent_type' => 'fuel_predictor',
            'prediction_type' => 'fuel_consumption',
            'team_id' => $team->id,
        ]);
        $this->assertNull($prediction->accuracy_score);
    }

    public function test_prediction_outcome_can_record_actual_result(): void
    {
        $team = Team::factory()->create();
        $prediction = AIPredictionOutcome::create([
            'agent_type' => 'fuel_predictor',
            'prediction_type' => 'fuel_consumption',
            'team_id' => $team->id,
            'predicted_value' => ['litres' => 150],
            'predicted_at' => now(),
            'confidence_score' => 0.9,
        ]);

        $prediction->recordOutcome(['litres' => 145], 0.97);

        $this->assertDatabaseHas('ai_prediction_outcomes', [
            'id' => $prediction->id,
            'accuracy_score' => 0.97,
            'false_positive' => 0,
        ]);
        $this->assertNotNull($prediction->fresh()->outcome_recorded_at);
    }

    public function test_reliability_score_returns_zero_with_no_data(): void
    {
        $service = app(PredictionAccuracyService::class);

        $this->assertEquals(0.0, $service->reliabilityScore());
    }

    public function test_reliability_score_scales_with_accuracy_data(): void
    {
        $team = Team::factory()->create();
        $service = app(PredictionAccuracyService::class);

        AIPredictionOutcome::create([
            'agent_type' => 'fleet_optimizer',
            'prediction_type' => 'route_time',
            'team_id' => $team->id,
            'predicted_value' => ['minutes' => 45],
            'predicted_at' => now(),
            'actual_value' => ['minutes' => 47],
            'outcome_recorded_at' => now(),
            'accuracy_score' => 0.8,
        ]);

        $score = $service->reliabilityScore();
        $this->assertGreaterThan(0, $score);
        $this->assertLessThanOrEqual(10, $score);
    }

    // ─────────────────────────────────────────────────────────────────────
    // DataQualitySnapshot — model + DataTrustService
    // ─────────────────────────────────────────────────────────────────────

    public function test_data_quality_snapshot_can_be_created(): void
    {
        DataQualitySnapshot::create([
            'domain' => 'fleet',
            'metric_name' => 'gps_coverage',
            'score' => 92.50,
            'total_records' => 40,
            'missing_count' => 3,
            'snapshot_at' => now(),
        ]);

        $this->assertDatabaseHas('data_quality_snapshots', [
            'domain' => 'fleet',
            'metric_name' => 'gps_coverage',
        ]);
    }

    public function test_overall_trust_score_returns_zero_with_no_snapshots(): void
    {
        $service = app(DataTrustService::class);

        $this->assertEquals(0.0, $service->overallTrustScore());
    }

    public function test_overall_trust_score_averages_latest_domain_snapshots(): void
    {
        DataQualitySnapshot::create([
            'domain' => 'fleet',
            'metric_name' => 'gps_coverage',
            'score' => 80.0,
            'total_records' => 10,
            'snapshot_at' => now(),
        ]);

        DataQualitySnapshot::create([
            'domain' => 'fuel',
            'metric_name' => 'transaction_integrity',
            'score' => 60.0,
            'total_records' => 5,
            'snapshot_at' => now(),
        ]);

        $service = app(DataTrustService::class);
        $score = $service->overallTrustScore();

        $this->assertEquals(70.0, $score);
    }

    // ─────────────────────────────────────────────────────────────────────
    // AgentPerformanceLog — model + AgentReliabilityService
    // ─────────────────────────────────────────────────────────────────────

    public function test_agent_log_can_be_created(): void
    {
        $service = app(AgentReliabilityService::class);

        $service->log(
            agentName: 'platform-guardian',
            operation: 'security_audit',
            status: 'success',
            confidenceScore: 0.95,
            evidenceCount: 12,
            findingCount: 2,
            executionTimeMs: 450,
            summary: 'Audit passed with 2 minor findings.',
        );

        $this->assertDatabaseHas('agent_performance_logs', [
            'agent_name' => 'platform-guardian',
            'operation' => 'security_audit',
            'status' => 'success',
        ]);
    }

    public function test_agent_score_returns_zero_with_no_logs(): void
    {
        $service = app(AgentReliabilityService::class);

        $this->assertEquals(0.0, $service->agentScore('non-existent-agent'));
    }

    public function test_agent_score_rewards_successful_operations(): void
    {
        $service = app(AgentReliabilityService::class);

        foreach (range(1, 5) as $_) {
            $service->log('test-agent', 'check', 'success', 0.9, 10, 1);
        }

        $score = $service->agentScore('test-agent');
        $this->assertGreaterThan(70, $score);
    }

    public function test_platform_reliability_score_returns_zero_with_no_logs(): void
    {
        $service = app(AgentReliabilityService::class);

        $this->assertEquals(0.0, $service->platformReliabilityScore());
    }

    // ─────────────────────────────────────────────────────────────────────
    // KnowledgeGraphEntry — model + OrganisationalMemoryService
    // ─────────────────────────────────────────────────────────────────────

    public function test_knowledge_fact_can_be_remembered(): void
    {
        $service = app(OrganisationalMemoryService::class);

        $service->remember(
            entryType: 'machine',
            subject: 'Machine:TRK-001',
            predicate: 'last_failure_mode',
            object: 'hydraulic_pump_seal',
            sourceAgent: 'maintenance-guardian',
            confidence: 95.0,
        );

        $this->assertDatabaseHas('knowledge_graph_entries', [
            'entry_type' => 'machine',
            'subject' => 'Machine:TRK-001',
            'predicate' => 'last_failure_mode',
            'object' => 'hydraulic_pump_seal',
            'is_active' => true,
        ]);
    }

    public function test_recall_returns_correct_fact(): void
    {
        $service = app(OrganisationalMemoryService::class);

        $service->remember('fleet', 'Fleet:Main', 'peak_fuel_risk', '06:00–08:00', 'fuel-guardian');

        $recalled = $service->recall('fleet', 'Fleet:Main', 'peak_fuel_risk');

        $this->assertEquals('06:00–08:00', $recalled);
    }

    public function test_remember_overwrites_previous_active_entry(): void
    {
        $service = app(OrganisationalMemoryService::class);

        $service->remember('machine', 'Machine:EX-005', 'status', 'idle', 'fleet-manager');
        $service->remember('machine', 'Machine:EX-005', 'status', 'active', 'fleet-manager');

        $recalled = $service->recall('machine', 'Machine:EX-005', 'status');
        $this->assertEquals('active', $recalled);

        $activeCount = KnowledgeGraphEntry::where('subject', 'Machine:EX-005')
            ->where('predicate', 'status')
            ->where('is_active', true)
            ->count();
        $this->assertEquals(1, $activeCount);
    }

    public function test_forget_deactivates_fact(): void
    {
        $service = app(OrganisationalMemoryService::class);

        $service->remember('route', 'Route:A3', 'avg_cycle_time', '47 minutes', 'fleet-manager');
        $service->forget('route', 'Route:A3', 'avg_cycle_time');

        $recalled = $service->recall('route', 'Route:A3', 'avg_cycle_time');
        $this->assertNull($recalled);
    }

    public function test_memory_health_score_is_zero_with_no_entries(): void
    {
        $service = app(OrganisationalMemoryService::class);

        $this->assertEquals(0.0, $service->memoryHealthScore());
    }

    public function test_memory_health_increases_with_entries(): void
    {
        $service = app(OrganisationalMemoryService::class);

        foreach (range(1, 10) as $i) {
            $service->remember('machine', "Machine:{$i}", 'status', 'active', 'fleet-manager');
        }

        $this->assertGreaterThan(0, $service->memoryHealthScore());
    }

    // ─────────────────────────────────────────────────────────────────────
    // MEGA V2 Artisan Command
    // ─────────────────────────────────────────────────────────────────────

    public function test_mega_score_command_runs_successfully(): void
    {
        $this->artisan('platform:mega-score')
            ->assertExitCode(0);
    }

    public function test_mega_score_command_json_flag_outputs_valid_json(): void
    {
        $this->artisan('platform:mega-score', ['--json' => true])
            ->assertExitCode(0);
    }

    public function test_mega_score_command_with_snapshot_flag(): void
    {
        $this->artisan('platform:mega-score', ['--snapshot' => true])
            ->assertExitCode(0);
    }
}
