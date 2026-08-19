<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Only create table if it doesn't already exist
        if (! Schema::hasTable('production_records')) {
            Schema::create('production_records', function (Blueprint $table) {
                $table->id();
                $table->foreignId('team_id')->constrained()->cascadeOnDelete();
                $table->foreignId('mine_area_id')->nullable()->constrained('mine_areas')->cascadeOnDelete();
                $table->foreignId('machine_id')->nullable()->constrained()->cascadeOnDelete();
                $table->date('record_date');
                $table->string('shift')->default('day'); // day, night, continuous
                $table->decimal('quantity_produced', 12, 2)->default(0);
                $table->string('unit')->default('tonnes'); // tonnes, cubic_meters, etc
                $table->decimal('target_quantity', 12, 2)->nullable();
                $table->text('notes')->nullable();
                $table->enum('status', ['completed', 'in-progress', 'pending', 'paused'])->default('completed');
                $table->json('metadata')->nullable(); // Additional data like quality metrics
                $table->timestamps();
                $table->softDeletes();

                $table->index('team_id');
                $table->index('mine_area_id');
                $table->index('machine_id');
                $table->index('record_date');
                $table->index('status');
            });
        }

        if (! Schema::hasTable('production_targets')) {
            Schema::create('production_targets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('team_id')->constrained()->cascadeOnDelete();
                $table->foreignId('mine_area_id')->nullable()->constrained('mine_areas')->cascadeOnDelete();
                $table->string('period_type')->default('daily'); // daily, weekly, monthly, quarterly, yearly
                $table->date('start_date');
                $table->date('end_date');
                $table->decimal('target_quantity', 12, 2);
                $table->string('unit')->default('tonnes');
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->index('team_id');
                $table->index('mine_area_id');
                $table->index('period_type');
            });
        }

        if (! Schema::hasTable('production_forecasts')) {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('production_forecasts');
        Schema::dropIfExists('production_targets');
        Schema::dropIfExists('production_records');
    }
};
