<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bell OEM Intelligence Platform – Phase 1-3 tables.
 *
 * Extends the base Bell fleet tables (created in 2026_06_04_182056) with
 * per-signal history tables, machine health history, and OEM caution codes.
 * Also adds a composite index to the existing telemetry history table.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ------------------------------------------------------------------ //
        // 1. Location History                                                 //
        // ------------------------------------------------------------------ //
        Schema::create('bell_equipment_location_history', function (Blueprint $table) {
            $table->bigIncrements('location_id');
            $table->unsignedBigInteger('equipment_key');
            $table->decimal('latitude', 18, 8)->nullable();
            $table->decimal('longitude', 18, 8)->nullable();
            $table->decimal('heading_degrees', 5, 2)->nullable();
            $table->decimal('speed_kmh', 8, 2)->nullable();
            $table->string('source', 20)->default('snapshot');  // 'snapshot' | 'historical_api'
            $table->dateTime('recorded_at');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('equipment_key')->references('equipment_key')->on('bell_equipment')->onDelete('cascade');
            $table->index(['equipment_key', 'recorded_at'], 'ix_bell_loc_equip_time');
        });

        // ------------------------------------------------------------------ //
        // 2. Fuel Usage History                                               //
        // ------------------------------------------------------------------ //
        Schema::create('bell_equipment_fuel_usage_history', function (Blueprint $table) {
            $table->bigIncrements('history_id');
            $table->unsignedBigInteger('equipment_key');
            $table->decimal('fuel_used_cumulative', 18, 2)->nullable();
            $table->decimal('fuel_remaining_percent', 5, 2)->nullable();
            $table->string('fuel_units', 20)->default('litre');
            $table->string('source', 20)->default('snapshot');
            $table->dateTime('recorded_at');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('equipment_key')->references('equipment_key')->on('bell_equipment')->onDelete('cascade');
            $table->index(['equipment_key', 'recorded_at'], 'ix_bell_fuel_equip_time');
        });

        // ------------------------------------------------------------------ //
        // 3. Operating Hours History                                          //
        // ------------------------------------------------------------------ //
        Schema::create('bell_equipment_operating_hours_history', function (Blueprint $table) {
            $table->bigIncrements('history_id');
            $table->unsignedBigInteger('equipment_key');
            $table->decimal('operating_hours', 18, 2)->nullable();
            $table->string('source', 20)->default('snapshot');
            $table->dateTime('recorded_at');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('equipment_key')->references('equipment_key')->on('bell_equipment')->onDelete('cascade');
            $table->index(['equipment_key', 'recorded_at'], 'ix_bell_ophrs_equip_time');
        });

        // ------------------------------------------------------------------ //
        // 4. Idle Hours History                                               //
        // ------------------------------------------------------------------ //
        Schema::create('bell_equipment_idle_hours_history', function (Blueprint $table) {
            $table->bigIncrements('history_id');
            $table->unsignedBigInteger('equipment_key');
            $table->decimal('idle_hours', 18, 2)->nullable();
            $table->string('source', 20)->default('snapshot');
            $table->dateTime('recorded_at');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('equipment_key')->references('equipment_key')->on('bell_equipment')->onDelete('cascade');
            $table->index(['equipment_key', 'recorded_at'], 'ix_bell_idle_equip_time');
        });

        // ------------------------------------------------------------------ //
        // 5. Load Count History                                               //
        // ------------------------------------------------------------------ //
        Schema::create('bell_equipment_load_count_history', function (Blueprint $table) {
            $table->bigIncrements('history_id');
            $table->unsignedBigInteger('equipment_key');
            $table->unsignedBigInteger('load_count')->nullable();
            $table->decimal('cumulative_payload', 18, 2)->nullable();
            $table->string('payload_units', 20)->default('kilogram');
            $table->string('source', 20)->default('snapshot');
            $table->dateTime('recorded_at');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('equipment_key')->references('equipment_key')->on('bell_equipment')->onDelete('cascade');
            $table->index(['equipment_key', 'recorded_at'], 'ix_bell_loads_equip_time');
        });

        // ------------------------------------------------------------------ //
        // 6. Health History (engine condition, DEF, active regen, score)     //
        // ------------------------------------------------------------------ //
        Schema::create('bell_equipment_health_history', function (Blueprint $table) {
            $table->bigIncrements('health_id');
            $table->unsignedBigInteger('equipment_key');
            $table->string('engine_condition', 50)->nullable();       // e.g. 'OK', 'Warning', 'Critical'
            $table->decimal('def_remaining_percent', 5, 2)->nullable();
            $table->decimal('active_regen_hours', 18, 2)->nullable(); // Cumulative DPF regen hours
            $table->unsignedInteger('caution_code_count')->default(0);
            $table->decimal('health_score', 5, 2)->nullable();        // 0-100 derived score
            $table->dateTime('recorded_at');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('equipment_key')->references('equipment_key')->on('bell_equipment')->onDelete('cascade');
            $table->index(['equipment_key', 'recorded_at'], 'ix_bell_health_equip_time');
        });

        // ------------------------------------------------------------------ //
        // 7. Caution Codes (OEM fault management)                            //
        // ------------------------------------------------------------------ //
        Schema::create('bell_equipment_caution_codes', function (Blueprint $table) {
            $table->bigIncrements('fault_id');
            $table->unsignedBigInteger('equipment_key');
            $table->string('fault_code', 100);
            $table->string('fault_description', 500)->nullable();
            $table->string('severity', 20)->nullable();       // 'Critical', 'Warning', 'Info'
            $table->string('source', 20)->default('snapshot');
            $table->boolean('is_active')->default(true);
            $table->dateTime('occurred_at');
            $table->dateTime('cleared_at')->nullable();
            $table->timestamps();

            $table->foreign('equipment_key')->references('equipment_key')->on('bell_equipment')->onDelete('cascade');
            $table->index(['equipment_key', 'is_active'], 'ix_bell_fault_equip_active');
            $table->index(['equipment_key', 'occurred_at'], 'ix_bell_fault_equip_time');
        });

        // ------------------------------------------------------------------ //
        // Performance: composite index on existing telemetry history table   //
        // ------------------------------------------------------------------ //
        Schema::table('bell_equipment_telemetry_history', function (Blueprint $table) {
            $table->index(['equipment_key', 'snapshot_time'], 'ix_bell_tel_equip_snap');
        });
    }

    public function down(): void
    {
        Schema::table('bell_equipment_telemetry_history', function (Blueprint $table) {
            $table->dropIndex('ix_bell_tel_equip_snap');
        });

        Schema::dropIfExists('bell_equipment_caution_codes');
        Schema::dropIfExists('bell_equipment_health_history');
        Schema::dropIfExists('bell_equipment_load_count_history');
        Schema::dropIfExists('bell_equipment_idle_hours_history');
        Schema::dropIfExists('bell_equipment_operating_hours_history');
        Schema::dropIfExists('bell_equipment_fuel_usage_history');
        Schema::dropIfExists('bell_equipment_location_history');
    }
};
