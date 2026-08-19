<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Mirror the structure of machine_metrics for archived (cold) records.
        // Rows older than the configured retention window are moved here nightly
        // by the ArchiveOldMetricsJob, keeping the hot table lean and fast.
        Schema::create('machine_metrics_archive', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('original_id')->index(); // FK to original row
            $table->unsignedBigInteger('team_id')->index();
            $table->unsignedBigInteger('machine_id')->index();
            $table->float('latitude')->nullable();
            $table->float('longitude')->nullable();
            $table->float('speed')->nullable();
            $table->float('heading')->nullable();
            $table->float('altitude')->nullable();
            $table->float('engine_rpm')->nullable();
            $table->float('engine_temperature')->nullable();
            $table->float('coolant_temperature')->nullable();
            $table->float('oil_pressure')->nullable();
            $table->float('fuel_level')->nullable();
            $table->float('fuel_consumption_rate')->nullable();
            $table->float('throttle_position')->nullable();
            $table->float('battery_voltage')->nullable();
            $table->float('total_hours')->nullable();
            $table->float('idle_hours')->nullable();
            $table->float('load_weight')->nullable();
            $table->float('payload_capacity_used')->nullable();
            $table->float('tire_pressure_front_left')->nullable();
            $table->float('tire_pressure_front_right')->nullable();
            $table->float('tire_pressure_rear_left')->nullable();
            $table->float('tire_pressure_rear_right')->nullable();
            $table->text('raw_data')->nullable();
            $table->float('operating_hours')->nullable();
            $table->dateTime('recorded_at')->nullable();
            $table->timestamps(); // original created_at / updated_at
            $table->timestamp('archived_at')->useCurrent();

            $table->index(['team_id', 'recorded_at'], 'archive_team_recorded_at');
            $table->index(['machine_id', 'recorded_at'], 'archive_machine_recorded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('machine_metrics_archive');
    }
};
