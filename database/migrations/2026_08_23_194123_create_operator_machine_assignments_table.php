<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who is (and was) on which machine.
 *
 * Modelled on machine_mine_area_assignments, which already answers the same
 * shape of question for areas: open rows have unassigned_at NULL, history is
 * closed rows, and nothing is ever deleted -- "who operated this ADT during
 * Tuesday's night shift?" is a question about closed rows.
 *
 * The override columns are the audit trail the compliance gate requires: an
 * assignment that went through despite a failed eligibility check carries who
 * forced it, why, and what the failures were at that moment. The eligibility
 * snapshot matters because it is computed from credential rows that keep
 * changing -- without the snapshot, next month nobody can say what the
 * override actually overrode.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operator_machine_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operator_id')->constrained()->cascadeOnDelete();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();

            $table->string('shift')->nullable(); // day, night, or null = unscoped
            $table->timestamp('assigned_at');
            $table->timestamp('unassigned_at')->nullable();

            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('unassigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();

            $table->boolean('was_override')->default(false);
            $table->string('override_reason')->nullable();
            $table->json('overridden_failures')->nullable();

            $table->timestamps();

            // "Current operator of machine X" and "current machine of
            // operator Y" are the two hot lookups.
            $table->index(['machine_id', 'unassigned_at']);
            $table->index(['operator_id', 'unassigned_at']);
            $table->index(['team_id', 'assigned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operator_machine_assignments');
    }
};
