<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MEGA V2 — Agent Reliability Performance Log
 *
 * Every time a platform agent runs an operation this table captures
 * the outcome. Enables the MEGA V2 "Agent Reliability" (+4%) and
 * "Agent Collaboration" (+3%) scoring domains.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_performance_logs', function (Blueprint $table) {
            $table->id();
            $table->string('agent_name', 120)->index();                              // e.g. platform-guardian
            $table->string('operation', 160);                                        // e.g. security_audit, queue_health_check
            $table->enum('status', ['success', 'failure', 'partial'])->index();
            $table->float('confidence_score')->nullable();                           // 0.0 – 1.0
            $table->unsignedInteger('evidence_count')->default(0);                  // number of data points used
            $table->unsignedInteger('finding_count')->default(0);                   // issues found
            $table->unsignedInteger('execution_time_ms')->nullable();
            $table->boolean('is_false_positive')->default(false);
            $table->boolean('is_false_negative')->default(false);
            $table->text('summary')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->index();
            $table->timestamp('updated_at')->nullable();

            $table->index(['agent_name', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_performance_logs');
    }
};
