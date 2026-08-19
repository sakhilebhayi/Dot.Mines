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
        Schema::create('geofence_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('geofence_id')->constrained('geofences')->cascadeOnDelete();
            $table->foreignId('machine_id')->constrained('machines')->cascadeOnDelete();

            $table->timestamp('entry_time');
            $table->timestamp('exit_time')->nullable();

            $table->float('entry_latitude');
            $table->float('entry_longitude');
            $table->float('exit_latitude')->nullable();
            $table->float('exit_longitude')->nullable();

            $table->float('tonnage_loaded')->default(0);
            $table->string('material_type')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('team_id');
            $table->index('geofence_id');
            $table->index('machine_id');
            $table->index(['geofence_id', 'exit_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('geofence_entries');
    }
};
