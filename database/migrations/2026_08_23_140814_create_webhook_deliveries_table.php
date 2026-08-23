<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per delivery attempt sequence, so "did you send it?" has an answer.
 *
 * Without this a failed webhook is invisible: the receiver saw nothing and we
 * kept no record of trying. The payload is stored so a delivery can be
 * inspected and replayed exactly as it was first sent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_endpoint_id')->constrained()->cascadeOnDelete();

            $table->string('event');
            $table->json('payload');

            // pending -> delivered | failed. `failed` means every retry was
            // spent, not that a single attempt failed.
            $table->string('status')->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);

            $table->unsignedSmallInteger('response_status')->nullable();
            $table->string('error')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('next_attempt_at')->nullable();

            $table->timestamps();

            // The recent-deliveries list for one endpoint, newest first.
            $table->index(['webhook_endpoint_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
