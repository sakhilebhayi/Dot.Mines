<?php

namespace App\Providers;

use App\Services\AI\AIOptimizationService;
use App\Services\AI\AnomalyDetectorAgent;
use App\Services\AI\CostAnalyzerAgent;
use App\Services\AI\FleetOptimizerAgent;
use App\Services\AI\FuelPredictorAgent;
use App\Services\AI\MaintenancePredictorAgent;
use App\Services\AI\RouteAdvisorAgent;
use Illuminate\Support\ServiceProvider;

class AIServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register individual AI agents
        $this->app->singleton(FleetOptimizerAgent::class);
        $this->app->singleton(RouteAdvisorAgent::class);
        $this->app->singleton(FuelPredictorAgent::class);
        $this->app->singleton(MaintenancePredictorAgent::class);
        $this->app->singleton(CostAnalyzerAgent::class);
        $this->app->singleton(AnomalyDetectorAgent::class);

        // Register main AI service
        $this->app->singleton(AIOptimizationService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
