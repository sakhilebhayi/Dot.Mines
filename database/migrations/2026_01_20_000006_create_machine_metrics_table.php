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
        Schema::create('machine_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('machine_id')->constrained('machines')->cascadeOnDelete();

            // Location data
            $table->float('latitude')->nullable();
            $table->float('longitude')->nullable();
            $table->float('speed')->nullable();
            $table->float('heading')->nullable();
            $table->float('altitude')->nullable();

            // Engine data
            $table->float('engine_rpm')->nullable();
            $table->float('engine_temperature')->nullable();
            $table->float('coolant_temperature')->nullable();
            $table->float('oil_pressure')->nullable();

            // Fuel data
            $table->float('fuel_level')->nullable();
            $table->float('fuel_consumption_rate')->nullable();
            $table->float('throttle_position')->nullable();

            // Power data
            $table->float('battery_voltage')->nullable();

            // Hours and load
            $table->float('total_hours')->nullable();
            $table->float('idle_hours')->nullable();
            $table->float('load_weight')->nullable();
            $table->float('payload_capacity_used')->nullable();

            // Tire data
            $table->float('tire_pressure_front_left')->nullable();
            $table->float('tire_pressure_front_right')->nullable();
            $table->float('tire_pressure_rear_left')->nullable();
            $table->float('tire_pressure_rear_right')->nullable();

            // Raw manufacturer data
            $table->json('raw_data')->nullable();

            $table->timestamps();

            $table->index('team_id');
            $table->index('machine_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('machine_metrics');
    }
};
