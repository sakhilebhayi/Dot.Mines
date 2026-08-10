<?php

namespace Tests\Unit;

use App\Models\ProductionForecast;
use App\Models\Team;
use App\Services\ProductionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * production_forecasts previously had two competing migrations: an earlier
 * one (no team_id) that actually created the table, and a later one guarded
 * by `if (!Schema::hasTable(...))` that silently no-opped as a result. The
 * ProductionForecast model, ProductionService, and MineArea's relation are
 * all written against the (never-applied) team_id-scoped schema, so every
 * call through this service threw SQLSTATE[42703]: Undefined column
 * "team_id" -- surfacing as a hard 500 on every /production page load.
 * Covers the corrected schema (see the fix_production_forecasts_table_schema
 * migration) actually matches what the model and service expect.
 */
class ProductionServiceForecastsTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_recent_forecasts_returns_forecasts_scoped_to_team_and_date_window(): void
    {
        $team = Team::factory()->create();
        $otherTeam = Team::factory()->create();

        $withinWindow = ProductionForecast::create([
            'team_id' => $team->id,
            'forecast_date' => Carbon::today()->addDays(3),
            'forecasted_quantity' => 500,
            'unit' => 'tonnes',
            'confidence_level' => 82.5,
        ]);

        ProductionForecast::create([
            'team_id' => $team->id,
            'forecast_date' => Carbon::today()->addDays(30),
            'forecasted_quantity' => 400,
            'unit' => 'tonnes',
            'confidence_level' => 70,
        ]);

        ProductionForecast::create([
            'team_id' => $otherTeam->id,
            'forecast_date' => Carbon::today()->addDays(2),
            'forecasted_quantity' => 900,
            'unit' => 'tonnes',
            'confidence_level' => 90,
        ]);

        $forecasts = app(ProductionService::class)->getRecentForecasts($team->id, 7);

        $this->assertCount(1, $forecasts);
        $this->assertTrue($forecasts->first()->is($withinWindow));
    }
}
