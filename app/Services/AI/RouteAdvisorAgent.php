<?php

namespace App\Services\AI;

use App\Models\FuelTransaction;
use App\Models\Route;
use App\Models\Team;
use App\Services\RoutePlanningService;

/**
 * Route Advisor AI Agent
 * Analyzes routes and provides optimization recommendations
 */
class RouteAdvisorAgent
{
    public function __construct(
        protected RoutePlanningService $routePlanningService
    ) {}

    public function analyze(Team $team): array
    {
        $recommendations = [];
        $insights = [];

        $routes = Route::where('team_id', $team->id)
            ->where('status', 'active')
            ->with('waypoints')
            ->get();

        $pricePerLiter = $this->getAverageUnitPrice($team);

        foreach ($routes as $route) {
            // Analyze route efficiency
            $efficiency = $this->analyzeRouteEfficiency($route);

            if ($efficiency['improvement_possible'] > 15) {
                $recommendations[] = [
                    'category' => 'route',
                    'priority' => 'high',
                    'title' => "Route Optimization Opportunity: {$route->name}",
                    'description' => "Route can be optimized to save {$efficiency['time_savings']} minutes and {$efficiency['fuel_savings']} liters of fuel.",
                    'proposed_action' => "Reroute {$route->name} via the optimized path identified by the route advisor to capture the {$efficiency['time_savings']}-minute, {$efficiency['fuel_savings']}-liter savings above.",
                    'confidence_score' => 0.83,
                    'estimated_savings' => $pricePerLiter !== null ? $efficiency['fuel_savings'] * $pricePerLiter : null,
                    'estimated_efficiency_gain' => $efficiency['improvement_possible'],
                    'related_route_id' => $route->id,
                    'data' => $efficiency,
                ];
            }
        }

        return [
            'recommendations' => $recommendations,
            'insights' => $insights,
        ];
    }

    protected function analyzeRouteEfficiency(Route $route): array
    {
        $directDistance = $this->routePlanningService->calculateDistance(
            $route->start_latitude,
            $route->start_longitude,
            $route->end_latitude,
            $route->end_longitude
        );

        $actualDistance = $route->total_distance;
        $detourPercent = (($actualDistance - $directDistance) / $directDistance) * 100;

        $improvementPossible = max(0, $detourPercent - 10); // 10% detour is acceptable
        $timeSavings = $improvementPossible * 0.5; // minutes
        $fuelSavings = $improvementPossible * 0.3; // liters

        return [
            'direct_distance' => round($directDistance, 2),
            'actual_distance' => $actualDistance,
            'detour_percent' => round($detourPercent, 2),
            'improvement_possible' => round($improvementPossible, 2),
            'time_savings' => round($timeSavings, 2),
            'fuel_savings' => round($fuelSavings, 2),
        ];
    }

    /**
     * The team's own recent average price paid per liter, computed from
     * real transactions -- null (rather than a guessed constant) when
     * there's no real pricing data yet. Mirrors FuelPredictorAgent's
     * equivalent helper.
     */
    protected function getAverageUnitPrice(Team $team): ?float
    {
        $avg = FuelTransaction::where('team_id', $team->id)
            ->where('transaction_type', 'dispensing')
            ->whereNotNull('unit_price')
            ->where('unit_price', '>', 0)
            ->whereDate('transaction_date', '>=', now()->subDays(90))
            ->avg('unit_price');

        return $avg !== null ? (float) $avg : null;
    }
}
