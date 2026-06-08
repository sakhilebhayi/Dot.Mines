<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MEGA V2 — Organisational Memory / Knowledge Graph
 *
 * Stores structured agent-sourced knowledge as subject-predicate-object triples.
 * Feeds the "Organisational Memory" (+2%) and "Reality Alignment" (+3%) MEGA V2 domains.
 *
 * Example entries:
 *   - Machine TRK-001 → "last_failure_mode" → "hydraulic_pump_seal"
 *   - Fleet → "peak_fuel_risk_period" → "06:00–08:00 on shift 1"
 *   - Route A3 → "avg_cycle_time_minutes" → "47"
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_graph_entries', function (Blueprint $table) {
            $table->id();
            $table->string('entry_type', 80)->index();    // e.g. machine, route, fleet, team
            $table->string('subject', 200);               // e.g. "Machine:TRK-001"
            $table->string('predicate', 120);             // e.g. "last_failure_mode"
            $table->text('object');                       // e.g. "hydraulic_pump_seal"
            $table->decimal('confidence', 5, 2)->default(100.00); // 0.00 – 100.00
            $table->string('source_agent', 120)->index(); // agent that created this entry
            $table->timestamp('valid_from')->index();
            $table->timestamp('valid_until')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Compound uniqueness — only one active triple per (entry_type, subject, predicate)
            $table->index(['entry_type', 'subject', 'predicate', 'is_active']);
            $table->index(['source_agent', 'valid_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_graph_entries');
    }
};
