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
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('machine_id')->nullable()->constrained('machines')->cascadeOnDelete();

            $table->string('type'); // engine, fuel, maintenance, geofence, temperature, etc
            $table->string('title');
            $table->text('description');
            $table->string('priority'); // critical, high, medium, low
            $table->string('status')->default('active'); // active, acknowledged, resolved

            $table->timestamp('triggered_at')->useCurrent();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();

            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->cascadeOnDelete();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index('team_id');
            $table->index('machine_id');
            $table->index('status');
            $table->index('priority');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
