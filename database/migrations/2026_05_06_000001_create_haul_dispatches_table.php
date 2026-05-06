<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('haul_dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mine_area_id')->nullable()->constrained()->nullOnDelete();

            // Dispatch lifecycle status
            $table->string('status', 20)->default('idle');
            // loading | hauling | dumping | returning | idle | parked

            // Origin – loading point
            $table->string('origin_name')->nullable();
            $table->decimal('origin_latitude', 10, 7)->nullable();
            $table->decimal('origin_longitude', 10, 7)->nullable();

            // Destination – dumping / tip point
            $table->string('destination_name')->nullable();
            $table->decimal('destination_latitude', 10, 7)->nullable();
            $table->decimal('destination_longitude', 10, 7)->nullable();

            // Live position snapshot
            $table->decimal('current_latitude', 10, 7)->nullable();
            $table->decimal('current_longitude', 10, 7)->nullable();
            $table->decimal('current_heading', 5, 1)->nullable();    // degrees 0-360
            $table->decimal('current_speed_kmh', 5, 1)->default(0);

            // Payload & fuel
            $table->decimal('current_tonnage', 8, 2)->default(0);           // tonnes on board
            $table->decimal('current_fuel_level_litres', 8, 2)->nullable();
            $table->decimal('fuel_capacity_litres', 8, 2)->nullable();

            // Distance & ETA
            $table->decimal('total_distance_km', 8, 3)->nullable();
            $table->decimal('distance_remaining_km', 8, 3)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('estimated_arrival_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            // GPS breadcrumb trail [[ lat, lng ], ...]
            $table->json('path_coordinates')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['team_id', 'status']);
            $table->index(['team_id', 'completed_at']);
            $table->index(['machine_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('haul_dispatches');
    }
};
