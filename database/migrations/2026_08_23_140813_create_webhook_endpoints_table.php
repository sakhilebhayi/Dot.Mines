<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Outbound webhooks: where a team wants events delivered.
 *
 * The API documentation told integrators to poll for anything they needed to
 * react to. Polling a fleet API on a short interval to notice an alert that
 * fired two seconds ago is wasteful for them and for us; this is the push
 * side of that.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('url', 2048);
            $table->string('description')->nullable();

            // Encrypted at rest: it is the shared secret a receiver verifies
            // signatures with, and it is shown to the user exactly once.
            $table->text('secret');

            // Which events this endpoint wants. ["*"] means every event,
            // including ones added later.
            $table->json('events');

            $table->boolean('is_active')->default(true);

            // Delivery health, so a dead endpoint is visible before someone
            // notices missing data downstream.
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->string('last_failure_reason')->nullable();
            $table->timestamp('auto_disabled_at')->nullable();

            $table->timestamps();

            // Every fan-out asks the same question: which active endpoints
            // does this team have?
            $table->index(['team_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_endpoints');
    }
};
