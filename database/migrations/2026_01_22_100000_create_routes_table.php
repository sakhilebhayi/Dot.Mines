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
        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->foreignId('machine_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('mine_area_id')->nullable()->constrained()->onDelete('set null');
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('start_latitude', 10, 7);
            $table->decimal('start_longitude', 10, 7);
            $table->decimal('end_latitude', 10, 7);
            $table->decimal('end_longitude', 10, 7);
            $table->decimal('total_distance', 10, 2); // in kilometers
            $table->integer('estimated_time'); // in minutes
            $table->decimal('estimated_fuel', 10, 2); // in litres
            $table->enum('route_type', ['optimal', 'shortest', 'safest', 'custom'])->default('optimal');
            $table->enum('status', ['draft', 'active', 'archived'])->default('draft');
            $table->json('metadata')->nullable(); // Store additional route info
            $table->timestamps();

            $table->index(['team_id', 'status']);
            $table->index(['machine_id']);
            $table->index(['mine_area_id']);
        });

        Schema::create('waypoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained()->onDelete('cascade');
            $table->integer('sequence_order');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('waypoint_type')->default('standard'); // standard, geofence, fuel_station, loading_point, dump_point
            $table->string('name')->nullable();
            $table->text('notes')->nullable();
            $table->integer('estimated_time_from_previous')->nullable(); // in minutes
            $table->decimal('distance_from_previous', 10, 2)->nullable(); // in kilometers
            $table->timestamps();

            $table->index(['route_id', 'sequence_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('waypoints');
        Schema::dropIfExists('routes');
    }
};
