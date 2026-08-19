<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Production Loss Accountability: every period of lost productive time,
     * whether detected from telemetry (source=system) or recorded by an
     * authorised user (source=user), with reason taxonomy, lifecycle status,
     * and an append-only audit trail of changes.
     */
    public function up(): void
    {
        Schema::create('production_loss_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at');
            $table->decimal('lost_hours', 8, 2);
            $table->string('source', 10); // system | user
            $table->string('status', 30)->default('pending_classification'); // pending_classification | confirmed | disputed | resolved
            $table->string('category', 30)->nullable(); // mechanical | operational | planned | environmental | safety | other
            $table->string('reason', 60)->nullable();
            $table->text('notes')->nullable();
            $table->string('detection_basis')->nullable(); // human-readable basis for system detections
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('classified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('classified_at')->nullable();
            // Append-only change log: [{at, by, action, changes}] -- users
            // must never be able to silently rewrite loss history.
            $table->json('audit_log')->nullable();
            $table->timestamps();

            $table->index(['machine_id', 'started_at'], 'idx_loss_machine_started');
            $table->index(['team_id', 'status'], 'idx_loss_team_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_loss_events');
    }
};
