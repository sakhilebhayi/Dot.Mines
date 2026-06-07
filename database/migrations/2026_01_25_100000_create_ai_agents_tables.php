<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // AI Agents configuration table
        Schema::create('ai_agents', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Agent name
            $table->string('type'); // fleet_optimizer, route_advisor, fuel_predictor, maintenance_predictor, production_optimizer, cost_analyzer
            $table->text('description')->nullable();
            $table->string('status')->default('active'); // active, inactive, training
            $table->json('configuration')->nullable(); // Agent-specific settings
            $table->json('capabilities')->nullable(); // List of capabilities
            $table->float('accuracy_score')->default(0); // Model accuracy (0-1)
            $table->integer('predictions_made')->default(0);
            $table->integer('successful_predictions')->default(0);
            $table->timestamp('last_trained_at')->nullable();
            $table->timestamps();

            $table->index('type');
            $table->index('status');
        });

        // AI Recommendations table
        Schema::create('ai_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_agent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // User who requested
            $table->string('category'); // fleet, route, fuel, maintenance, production
            $table->string('priority'); // critical, high, medium, low
            $table->string('status')->default('pending'); // pending, accepted, rejected, implemented
            $table->string('title');
            $table->text('description');
            $table->json('data')->nullable(); // Detailed recommendation data
            $table->json('impact_analysis')->nullable(); // Expected impact metrics
            $table->float('confidence_score')->default(0); // AI confidence (0-1)
            $table->decimal('estimated_savings', 15, 2)->nullable(); // Cost savings in ZAR
            $table->decimal('estimated_efficiency_gain', 8, 2)->nullable(); // Efficiency improvement %
            $table->foreignId('related_machine_id')->nullable()->constrained('machines')->nullOnDelete();
            $table->foreignId('related_mine_area_id')->nullable()->constrained('mine_areas')->nullOnDelete();
            $table->foreignId('related_route_id')->nullable()->constrained('routes')->nullOnDelete();
            $table->timestamp('implemented_at')->nullable();
            $table->foreignId('implemented_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('implementation_notes')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'status']);
            $table->index(['category', 'priority']);
            $table->index('created_at');
        });

        // AI Analysis Sessions
        Schema::create('ai_analysis_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_agent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('analysis_type'); // real_time, scheduled, on_demand
            $table->string('status')->default('running'); // running, completed, failed
            $table->json('input_parameters')->nullable();
            $table->json('results')->nullable();
            $table->integer('recommendations_generated')->default(0);
            $table->integer('processing_time_ms')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'status']);
            $table->index('created_at');
        });

        // AI Learning Data (for continuous improvement)
        Schema::create('ai_learning_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_agent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recommendation_id')->nullable()->constrained('ai_recommendations')->cascadeOnDelete();
            $table->string('data_type'); // outcome, feedback, correction
            $table->json('input_data')->nullable();
            $table->json('predicted_output')->nullable();
            $table->json('actual_output')->nullable();
            $table->float('accuracy')->nullable();
            $table->boolean('was_accurate')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['ai_agent_id', 'was_accurate']);
        });

        // AI Insights Dashboard
        Schema::create('ai_insights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('insight_type'); // trend, anomaly, prediction, optimization
            $table->string('category'); // fleet, fuel, production, maintenance, cost
            $table->string('severity')->default('info'); // critical, warning, info, success
            $table->string('title');
            $table->text('description');
            $table->json('data')->nullable();
            $table->json('visualization_data')->nullable(); // Data for charts
            $table->boolean('is_read')->default(false);
            $table->timestamp('valid_until')->nullable(); // Insight expiry
            $table->timestamps();

            $table->index(['team_id', 'is_read']);
            $table->index(['category', 'severity']);
        });

        // AI Predictive Alerts
        Schema::create('ai_predictive_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_agent_id')->constrained()->cascadeOnDelete();
            $table->string('alert_type'); // breakdown_risk, fuel_shortage, production_delay, cost_overrun
            $table->string('severity'); // critical, high, medium, low
            $table->string('title');
            $table->text('description');
            $table->json('predictions')->nullable(); // Detailed predictions
            $table->float('probability')->default(0); // 0-1 probability
            $table->timestamp('predicted_occurrence')->nullable(); // When it will happen
            $table->json('recommended_actions')->nullable();
            $table->foreignId('related_machine_id')->nullable()->constrained('machines')->nullOnDelete();
            $table->foreignId('related_mine_area_id')->nullable()->constrained('mine_areas')->nullOnDelete();
            $table->boolean('is_acknowledged')->default(false);
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->boolean('was_accurate')->nullable(); // Filled after event
            $table->timestamps();

            $table->index(['team_id', 'is_acknowledged']);
            $table->index(['severity', 'predicted_occurrence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_predictive_alerts');
        Schema::dropIfExists('ai_insights');
        Schema::dropIfExists('ai_learning_data');
        Schema::dropIfExists('ai_analysis_sessions');
        Schema::dropIfExists('ai_recommendations');
        Schema::dropIfExists('ai_agents');
    }
};
