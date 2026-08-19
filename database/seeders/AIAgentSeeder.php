<?php

namespace Database\Seeders;

use App\Models\AIAgent;
use Illuminate\Database\Seeder;

class AIAgentSeeder extends Seeder
{
    public function run(): void
    {
        $agents = [
            [
                'name' => 'Fleet Optimizer',
                'type' => AIAgent::TYPE_FLEET_OPTIMIZER,
                'description' => 'Analyzes fleet utilization, identifies idle equipment, and optimizes machine allocation across mine areas. Uses historical data and real-time metrics to maximize fleet efficiency.',
                'status' => 'active',
                'capabilities' => ['machine_allocation', 'utilization_analysis', 'idle_time_reduction', 'area_optimization'],
                'accuracy_score' => 0.82,
                'configuration' => [
                    'analysis_interval_hours' => 6,
                    'min_utilization_threshold' => 30,
                    'max_utilization_threshold' => 95,
                    'idle_cost_per_day' => 5000,
                ],
            ],
            [
                'name' => 'Route Advisor',
                'type' => AIAgent::TYPE_ROUTE_ADVISOR,
                'description' => 'Recommends optimal routes between locations, considers geofences and obstacles, and identifies route efficiency improvements. Reduces travel time and fuel consumption.',
                'status' => 'active',
                'capabilities' => ['route_optimization', 'obstacle_avoidance', 'time_prediction', 'fuel_efficiency'],
                'accuracy_score' => 0.85,
                'configuration' => [
                    'acceptable_detour_percent' => 10,
                    'fuel_cost_per_liter' => 25,
                    'average_speed_kmh' => 40,
                ],
            ],
            [
                'name' => 'Fuel Predictor',
                'type' => AIAgent::TYPE_FUEL_PREDICTOR,
                'description' => 'Predicts fuel consumption patterns, detects anomalies in usage, forecasts inventory needs, and recommends optimal ordering schedules to prevent shortages.',
                'status' => 'active',
                'capabilities' => ['consumption_forecasting', 'anomaly_detection', 'inventory_management', 'cost_optimization'],
                'accuracy_score' => 0.88,
                'configuration' => [
                    'prediction_days' => 30,
                    'anomaly_threshold_percent' => 20,
                    'min_days_of_supply' => 7,
                    'buffer_percent' => 20,
                ],
            ],
            [
                'name' => 'Maintenance Predictor',
                'type' => AIAgent::TYPE_MAINTENANCE_PREDICTOR,
                'description' => 'Predicts maintenance needs and potential breakdowns using machine learning. Analyzes health scores, operating hours, and historical patterns to prevent costly failures.',
                'status' => 'active',
                'capabilities' => ['breakdown_prediction', 'health_monitoring', 'schedule_optimization', 'cost_prevention'],
                'accuracy_score' => 0.79,
                'configuration' => [
                    'risk_threshold_high' => 0.7,
                    'risk_threshold_medium' => 0.4,
                    'average_breakdown_cost' => 150000,
                    'preventive_maintenance_cost' => 25000,
                ],
            ],
            [
                'name' => 'Production Optimizer',
                'type' => AIAgent::TYPE_PRODUCTION_OPTIMIZER,
                'description' => 'Optimizes production schedules, forecasts output, and identifies underperforming areas. Recommends resource allocation to maximize productivity.',
                'status' => 'active',
                'capabilities' => ['output_forecasting', 'schedule_optimization', 'resource_allocation', 'performance_analysis'],
                'accuracy_score' => 0.81,
                'configuration' => [
                    'target_achievement_threshold' => 0.8,
                    'forecast_days' => 7,
                ],
            ],
            [
                'name' => 'Cost Analyzer',
                'type' => AIAgent::TYPE_COST_ANALYZER,
                'description' => 'Analyzes operational costs across all categories, identifies savings opportunities, and provides detailed cost breakdowns to optimize budget allocation.',
                'status' => 'active',
                'capabilities' => ['cost_breakdown', 'savings_identification', 'budget_optimization', 'trend_analysis'],
                'accuracy_score' => 0.84,
                'configuration' => [
                    'high_cost_threshold_daily' => 50000,
                    'analysis_period_days' => 30,
                    'savings_target_percent' => 15,
                ],
            ],
            [
                'name' => 'Anomaly Detector',
                'type' => AIAgent::TYPE_ANOMALY_DETECTOR,
                'description' => 'Continuously monitors all systems for unusual patterns, unexpected behaviors, and potential issues. Provides early warnings for operational anomalies.',
                'status' => 'active',
                'capabilities' => ['pattern_detection', 'outlier_identification', 'risk_assessment', 'early_warning'],
                'accuracy_score' => 0.77,
                'configuration' => [
                    'monitoring_interval_minutes' => 15,
                    'data_staleness_hours' => 24,
                ],
            ],
        ];

        foreach ($agents as $agent) {
            AIAgent::updateOrCreate(
                ['type' => $agent['type']],
                $agent
            );
        }

        $this->command->info('AI Agents seeded successfully!');
    }
}
