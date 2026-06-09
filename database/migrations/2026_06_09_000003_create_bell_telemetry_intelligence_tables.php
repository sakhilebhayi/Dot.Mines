<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bell Telemetry Intelligence Tables
 *
 * Adds five dedicated per-signal telemetry tables for signals that were previously
 * either not stored (Distance) or co-located in aggregate tables:
 *
 *   bell_distance_travelled     ← Distance endpoint
 *   bell_payload_totals         ← CumulativePayloadTotals endpoint
 *   bell_def_levels             ← DEFRemaining endpoint
 *   bell_fuel_levels            ← FuelRemainingRatio endpoint
 *   bell_regeneration_hours     ← CumulativeActiveRegenerationHours endpoint
 *
 * All tables use equipment_key (FK → bell_equipment.equipment_key) as the join key,
 * consistent with the existing Bell telemetry table conventions.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Distance travelled ─────────────────────────────────────────── //
        Schema::create('bell_distance_travelled', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('equipment_key');
            $table->decimal('distance_km', 12, 3)->comment('Cumulative distance in km as reported by the Bell Distance endpoint');
            $table->timestamp('snapshot_time')->nullable()->index();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('equipment_key')
                ->references('equipment_key')
                ->on('bell_equipment')
                ->cascadeOnDelete();

            $table->index('equipment_key');
        });

        // ── Payload totals ─────────────────────────────────────────────── //
        Schema::create('bell_payload_totals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('equipment_key');
            $table->decimal('payload_tonnes', 14, 3)->comment('Cumulative payload in tonnes from CumulativePayloadTotals endpoint');
            $table->timestamp('snapshot_time')->nullable()->index();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('equipment_key')
                ->references('equipment_key')
                ->on('bell_equipment')
                ->cascadeOnDelete();

            $table->index('equipment_key');
        });

        // ── DEF (Diesel Exhaust Fluid) levels ──────────────────────────── //
        Schema::create('bell_def_levels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('equipment_key');
            $table->decimal('def_remaining_percent', 5, 2)
                ->comment('DEF tank remaining percentage (0–100) from DEFRemaining endpoint');
            $table->timestamp('snapshot_time')->nullable()->index();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('equipment_key')
                ->references('equipment_key')
                ->on('bell_equipment')
                ->cascadeOnDelete();

            $table->index('equipment_key');
        });

        // ── Fuel levels ────────────────────────────────────────────────── //
        Schema::create('bell_fuel_levels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('equipment_key');
            $table->decimal('fuel_remaining_percent', 5, 2)
                ->comment('Fuel remaining percentage (0–100) from FuelRemainingRatio endpoint');
            $table->timestamp('snapshot_time')->nullable()->index();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('equipment_key')
                ->references('equipment_key')
                ->on('bell_equipment')
                ->cascadeOnDelete();

            $table->index('equipment_key');
        });

        // ── DPF regeneration hours ─────────────────────────────────────── //
        Schema::create('bell_regeneration_hours', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('equipment_key');
            $table->decimal('regeneration_hours', 10, 2)
                ->comment('Cumulative active DPF regeneration hours from CumulativeActiveRegenerationHours endpoint');
            $table->timestamp('snapshot_time')->nullable()->index();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('equipment_key')
                ->references('equipment_key')
                ->on('bell_equipment')
                ->cascadeOnDelete();

            $table->index('equipment_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bell_regeneration_hours');
        Schema::dropIfExists('bell_fuel_levels');
        Schema::dropIfExists('bell_def_levels');
        Schema::dropIfExists('bell_payload_totals');
        Schema::dropIfExists('bell_distance_travelled');
    }
};
