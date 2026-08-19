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
        Schema::create('geofences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('mine_area_id')->nullable()->constrained('mine_areas')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type'); // pit, stockpile, dump, facility
            $table->json('coordinates'); // polygon coordinates
            $table->float('center_latitude');
            $table->float('center_longitude');
            $table->float('area_sqm')->nullable();
            $table->float('perimeter_m')->nullable();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('team_id');
            $table->index('mine_area_id');
            $table->index('type');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('geofences');
    }
};
