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
        Schema::create('machines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->string('name');
            $table->string('machine_type'); // volvo, cat, komatsu, bell, ldv
            $table->string('model')->nullable();
            $table->string('registration_number')->unique();
            $table->string('serial_number')->unique();
            $table->string('manufacturer_id')->nullable();
            $table->float('capacity')->nullable(); // tonnes
            $table->float('fuel_capacity')->nullable(); // litres
            $table->float('hours_meter')->default(0);
            $table->string('status')->default('active'); // active, idle, maintenance, offline
            $table->float('last_location_latitude')->nullable();
            $table->float('last_location_longitude')->nullable();
            $table->timestamp('last_location_update')->nullable();
            $table->foreignId('integration_id')->nullable()->constrained('integrations')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('team_id');
            $table->index('machine_type');
            $table->index('status');
            $table->index('registration_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('machines');
    }
};
