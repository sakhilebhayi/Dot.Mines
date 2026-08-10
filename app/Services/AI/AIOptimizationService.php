<?php

namespace App\Services\AI;

use App\Models\AIAgent;
use App\Models\AIAnalysisSession;
use App\Models\AIInsight;
use App\Models\AIRecommendation;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Core AI Service for mining optimization
 * Coordinates all AI agents and provides unified interface
 */
class AIOptimizationService
{
    protected array $agents = [];

    public function __construct(
        protected FleetOptimizerAgent $fleetOptimizer,
        protected RouteAdvisorAgent $routeAdvisor,
        protected FuelPredictorAgent $fuelPredictor,
        protected MaintenancePredictorAgent $maintenancePredictor,
        protected CostAnalyzerAgent $costAnalyzer,
        protected AnomalyDetectorAgent $anomalyDetector,
        protected DispatchAdvisorAgent $dispatchAdvisor
    ) {
        $this->agents = [
            AIAgent::TYPE_FLEET_OPTIMIZER => $fleetOptimizer,
            AIAgent::TYPE_ROUTE_ADVISOR => $routeAdvisor,
            AIAgent::TYPE_FUEL_PREDICTOR => $fuelPredictor,
            AIAgent::TYPE_MAINTENANCE_PREDICTOR => $maintenancePredictor,
            AIAgent::TYPE_COST_ANALYZER => $costAnalyzer,
            AIAgent::TYPE_ANOMALY_DETECTOR => $anomalyDetector,
            AIAgent::TYPE_DISPATCH_ADVISOR => $dispatchAdvisor,
        ];
    }

    /**
     * Run comprehensive analysis across all AI agents
     */
    public function runComprehensiveAnalysis(Team $team, ?User $user = null): Collection
    {
        $recommendations = collect();
        $insights = collect();

        foreach ($this->agents as $type => $agent) {
            $agentModel = $this->getOrCreateAgent($type);

            if (! $agentModel->isActive()) {
                continue;
            }

            // Create analysis session
            $session = AIAnalysisSession::create([
                'team_id' => $team->id,
                'ai_agent_id' => $agentModel->id,
                'user_id' => $user?->id,
                'analysis_type' => 'on_demand',
                'status' => 'running',
                'started_at' => now(),
            ]);

            try {
                // Run agent analysis
                $result = $agent->analyze($team);

                // Store recommendations
                foreach ($result['recommendations'] as $rec) {
                    $recommendation = AIRecommendation::create([
                        'team_id' => $team->id,
                        'ai_agent_id' => $agentModel->id,
                        'user_id' => $user?->id,
                        'category' => $rec['category'],
                        'priority' => $rec['priority'],
                        'title' => $rec['title'],
                        'description' => $rec['description'],
                        'proposed_action' => $rec['proposed_action'] ?? $rec['description'],
                        'data' => $rec['data'] ?? [],
                        'impact_analysis' => $rec['impact_analysis'] ?? [],
                        'confidence_score' => $rec['confidence_score'],
                        'estimated_savings' => $rec['estimated_savings'] ?? null,
                        'estimated_efficiency_gain' => $rec['estimated_efficiency_gain'] ?? null,
                        'related_machine_id' => $rec['related_machine_id'] ?? null,
                        'related_mine_area_id' => $rec['related_mine_area_id'] ?? null,
                        'related_route_id' => $rec['related_route_id'] ?? null,
                    ]);

                    $recommendations->push($recommendation);
                }

                // Store insights
                foreach ($result['insights'] as $ins) {
                    $insight = AIInsight::create([
                        'team_id' => $team->id,
                        'insight_type' => $ins['type'],
                        'category' => $ins['category'],
                        'severity' => $ins['severity'],
                        'title' => $ins['title'],
                        'description' => $ins['description'],
                        'data' => $ins['data'] ?? [],
                        'visualization_data' => $ins['visualization_data'] ?? [],
                        'valid_until' => $ins['valid_until'] ?? null,
                    ]);

                    $insights->push($insight);
                }

                // Mark session as completed
                $session->markAsCompleted($result, count($result['recommendations']));

            } catch (\Exception $e) {
                $session->markAsFailed($e->getMessage());
            }
        }

        return collect([
            'recommendations' => $recommendations,
            'insights' => $insights,
            'summary' => $this->generateSummary($recommendations, $insights),
        ]);
    }

    /**
     * Get recommendations for a specific category
     */
    public function getRecommendationsForCategory(Team $team, string $category, ?User $user = null): Collection
    {
        $agentType = $this->getAgentTypeForCategory($category);
        $agent = $this->agents[$agentType] ?? null;

        if (! $agent) {
            return collect();
        }

        $agentModel = $this->getOrCreateAgent($agentType);
        $result = $agent->analyze($team);

        return collect($result['recommendations'])->map(function ($rec) use ($team, $agentModel, $user) {
            return AIRecommendation::create([
                'team_id' => $team->id,
                'ai_agent_id' => $agentModel->id,
                'user_id' => $user?->id,
                'category' => $rec['category'],
                'priority' => $rec['priority'],
                'title' => $rec['title'],
                'description' => $rec['description'],
                'proposed_action' => $rec['proposed_action'] ?? $rec['description'],
                'data' => $rec['data'] ?? [],
                'impact_analysis' => $rec['impact_analysis'] ?? [],
                'confidence_score' => $rec['confidence_score'],
                'estimated_savings' => $rec['estimated_savings'] ?? null,
                'estimated_efficiency_gain' => $rec['estimated_efficiency_gain'] ?? null,
            ]);
        });
    }

    /**
     * Get AI insights dashboard
     */
    public function getDashboardInsights(Team $team): array
    {
        $insights = AIInsight::where('team_id', $team->id)
            ->valid()
            ->orderBy('severity')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        $recommendations = AIRecommendation::where('team_id', $team->id)
            ->where('status', 'pending')
            ->highPriority()
            ->orderBy('priority')
            ->orderBy('confidence_score', 'desc')
            ->limit(10)
            ->get();

        $stats = [
            'total_recommendations' => AIRecommendation::where('team_id', $team->id)->count(),
            'pending_recommendations' => AIRecommendation::where('team_id', $team->id)->pending()->count(),
            'implemented_recommendations' => AIRecommendation::where('team_id', $team->id)->where('status', 'implemented')->count(),
            'total_savings' => AIRecommendation::where('team_id', $team->id)
                ->where('status', 'implemented')
                ->sum('estimated_savings'),
            'potential_savings' => AIRecommendation::where('team_id', $team->id)
                ->where('status', 'pending')
                ->sum('estimated_savings'),
            'average_confidence' => AIRecommendation::where('team_id', $team->id)
                ->avg('confidence_score'),
            'active_agents' => AIAgent::active()->count(),
        ];

        return [
            'insights' => $insights,
            'recommendations' => $recommendations,
            'stats' => $stats,
        ];
    }

    /**
     * Get or create AI agent model
     */
    protected function getOrCreateAgent(string $type): AIAgent
    {
        return AIAgent::firstOrCreate(
            ['type' => $type],
            [
                'name' => $this->getAgentName($type),
                'description' => $this->getAgentDescription($type),
                'status' => 'active',
                'capabilities' => $this->getAgentCapabilities($type),
                // No accuracy_score here: it defaults to 0 in the schema and
                // is only ever meaningful once AIPredictiveAlert::recordAccuracy()
                // has logged real outcomes (predictions_made > 0). A hardcoded
                // "initial score" here would show as a fixed, never-changing
                // percentage on the AI Analytics page regardless of how the
                // agent actually performs.
            ]
        );
    }

    protected function getAgentName(string $type): string
    {
        return match ($type) {
            AIAgent::TYPE_FLEET_OPTIMIZER => 'Fleet Optimizer',
            AIAgent::TYPE_ROUTE_ADVISOR => 'Route Advisor',
            AIAgent::TYPE_FUEL_PREDICTOR => 'Fuel Predictor',
            AIAgent::TYPE_MAINTENANCE_PREDICTOR => 'Maintenance Predictor',
            AIAgent::TYPE_PRODUCTION_OPTIMIZER => 'Production Optimizer',
            AIAgent::TYPE_COST_ANALYZER => 'Cost Analyzer',
            AIAgent::TYPE_ANOMALY_DETECTOR => 'Anomaly Detector',
            AIAgent::TYPE_DISPATCH_ADVISOR => 'Dispatch Advisor',
            default => 'Unknown Agent',
        };
    }

    protected function getAgentDescription(string $type): string
    {
        return match ($type) {
            AIAgent::TYPE_FLEET_OPTIMIZER => 'Optimizes fleet allocation and machine utilization',
            AIAgent::TYPE_ROUTE_ADVISOR => 'Recommends optimal routes and identifies bottlenecks',
            AIAgent::TYPE_FUEL_PREDICTOR => 'Predicts fuel consumption and identifies savings opportunities',
            AIAgent::TYPE_MAINTENANCE_PREDICTOR => 'Predicts maintenance needs and prevents breakdowns',
            AIAgent::TYPE_PRODUCTION_OPTIMIZER => 'Optimizes production schedules and forecasts output',
            AIAgent::TYPE_COST_ANALYZER => 'Analyzes costs and identifies optimization opportunities',
            AIAgent::TYPE_ANOMALY_DETECTOR => 'Detects unusual patterns and potential issues',
            AIAgent::TYPE_DISPATCH_ADVISOR => 'Flags live queue imbalances and recommends active reroutes between loading/dump points',
            default => '',
        };
    }

    protected function getAgentCapabilities(string $type): array
    {
        return match ($type) {
            AIAgent::TYPE_FLEET_OPTIMIZER => ['machine_allocation', 'utilization_analysis', 'idle_time_reduction'],
            AIAgent::TYPE_ROUTE_ADVISOR => ['route_optimization', 'traffic_analysis', 'time_prediction'],
            AIAgent::TYPE_FUEL_PREDICTOR => ['consumption_forecasting', 'efficiency_analysis', 'cost_prediction'],
            AIAgent::TYPE_MAINTENANCE_PREDICTOR => ['breakdown_prediction', 'health_monitoring', 'schedule_optimization'],
            AIAgent::TYPE_PRODUCTION_OPTIMIZER => ['output_forecasting', 'schedule_optimization', 'resource_allocation'],
            AIAgent::TYPE_COST_ANALYZER => ['cost_breakdown', 'savings_identification', 'budget_optimization'],
            AIAgent::TYPE_ANOMALY_DETECTOR => ['pattern_detection', 'outlier_identification', 'risk_assessment'],
            AIAgent::TYPE_DISPATCH_ADVISOR => ['queue_monitoring', 'live_reroute_recommendation', 'dwell_time_analysis'],
            default => [],
        };
    }

    protected function getAgentTypeForCategory(string $category): string
    {
        return match ($category) {
            'fleet' => AIAgent::TYPE_FLEET_OPTIMIZER,
            'route' => AIAgent::TYPE_ROUTE_ADVISOR,
            'fuel' => AIAgent::TYPE_FUEL_PREDICTOR,
            'maintenance' => AIAgent::TYPE_MAINTENANCE_PREDICTOR,
            'production' => AIAgent::TYPE_PRODUCTION_OPTIMIZER,
            'cost' => AIAgent::TYPE_COST_ANALYZER,
            'dispatch' => AIAgent::TYPE_DISPATCH_ADVISOR,
            default => AIAgent::TYPE_ANOMALY_DETECTOR,
        };
    }

    protected function generateSummary(Collection $recommendations, Collection $insights): array
    {
        return [
            'total_recommendations' => $recommendations->count(),
            'critical_recommendations' => $recommendations->where('priority', 'critical')->count(),
            'high_priority_recommendations' => $recommendations->where('priority', 'high')->count(),
            'total_estimated_savings' => $recommendations->sum('estimated_savings'),
            'average_confidence' => $recommendations->avg('confidence_score'),
            'total_insights' => $insights->count(),
            'critical_insights' => $insights->where('severity', 'critical')->count(),
        ];
    }
}
