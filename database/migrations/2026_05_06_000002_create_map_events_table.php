<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('map_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('machine_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('mine_area_id')->nullable()->constrained()->nullOnDelete();

            // Event classification
            // loading | dumping | breakdown | idling | maintenance | fueling |
            // geofence_entry | geofence_exit | speed_violation | status_change | other
            $table->string('event_type', 40);

            // Human-readable title / short description
            $table->string('title');
            $table->text('notes')->nullable();

            // Precise location where the event occurred
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // Event lifecycle
            $table->timestamp('occurred_at');
            $table->timestamp('resolved_at')->nullable();

            // E.g. speed reading, tonnage, fuel level, old/new status, etc.
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['team_id', 'event_type']);
            $table->index(['team_id', 'occurred_at']);
            $table->index(['machine_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('map_events');
    }
};
