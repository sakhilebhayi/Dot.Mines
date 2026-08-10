<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two earlier migrations both create a `production_forecasts` table with
 * incompatible schemas:
 *   - 2026_01_20_create_iot_and_forecasting_tables: mine_area_id, forecast_date,
 *     material_name, predicted_tonnage, confidence_score, model_version, factors
 *     (no team_id).
 *   - 2026_02_12_000002_create_production_tables: guards with
 *     `if (!Schema::hasTable('production_forecasts'))`, so it silently no-ops
 *     once the January migration has already created the table -- its
 *     team_id/forecasted_quantity/unit/confidence_level/forecast_method
 *     schema never actually gets applied.
 *
 * App\Models\ProductionForecast's $fillable/$casts, ProductionService, and
 * MineArea::productionForecasts() are all written against the February
 * schema (team-scoped, matching this app's team-based multi-tenancy
 * everywhere else). The live table has the January schema instead, so any
 * query through the model -- e.g. ProductionService::getRecentForecasts(),
 * called on every /production page load -- fails with
 * SQLSTATE[42703]: Undefined column: "team_id" does not exist.
 *
 * The table is empty (never-used dead schema), so it's dropped and
 * recreated with the schema the application code actually expects.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('production_forecasts');

        Schema::create('production_forecasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mine_area_id')->nullable()->constrained('mine_areas')->cascadeOnDelete();
            $table->date('forecast_date');
            $table->decimal('forecasted_quantity', 12, 2);
            $table->string('unit')->default('tonnes');
            $table->decimal('confidence_level', 5, 2); // 0-100%
            $table->json('forecast_method')->nullable(); // Which AI/method generated forecast
            $table->timestamps();

            $table->index('team_id');
            $table->index('mine_area_id');
            $table->index('forecast_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_forecasts');

        Schema::create('production_forecasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mine_area_id')->constrained('mine_areas')->onDelete('cascade');
            $table->date('forecast_date');
            $table->string('material_name');
            $table->decimal('predicted_tonnage', 10, 2);
            $table->float('confidence_score')->default(0.0);
            $table->string('model_version')->default('1.0');
            $table->json('factors')->nullable();
            $table->timestamps();

            $table->index('mine_area_id');
            $table->index('forecast_date');
            $table->unique(['mine_area_id', 'forecast_date', 'material_name']);
        });
    }
};
