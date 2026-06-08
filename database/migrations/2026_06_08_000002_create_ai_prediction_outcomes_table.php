<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MEGA V2 — AI Prediction Accuracy Tracking
 *
 * Records prediction-vs-outcome pairs for every AI agent.
 * Enables drift detection, accuracy scoring, and MEGA V2
 * "AI Reliability" and "AI Drift Control" domain scoring.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_prediction_outcomes', function (Blueprint $table) {
            $table->id();
            $table->string('agent_type', 80)->index();           // e.g. fleet_optimizer, fuel_predictor
            $table->string('prediction_type', 80)->index();      // e.g. maintenance_due, fuel_consumption
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('machine_id')->nullable()->index();
            // Prediction
            $table->json('predicted_value');                     // flexible: numeric, category, range
            $table->timestamp('predicted_at')->index();
            $table->float('confidence_score')->nullable();       // 0.0 – 1.0
            // Outcome (filled when reality is known)
            $table->json('actual_value')->nullable();
            $table->timestamp('outcome_recorded_at')->nullable();
            // Accuracy (calculated when outcome arrives)
            $table->float('accuracy_score')->nullable()->index(); // 0.0 – 1.0
            $table->boolean('false_positive')->default(false);
            $table->boolean('false_negative')->default(false);
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['agent_type', 'prediction_type', 'predicted_at']);
            $table->index(['team_id', 'agent_type', 'accuracy_score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_prediction_outcomes');
    }
};
