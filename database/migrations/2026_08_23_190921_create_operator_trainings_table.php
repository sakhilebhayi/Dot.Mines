<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Training and competency: site induction, machine-specific competency,
 * safety and emergency courses, and their refresher dates.
 *
 * Separate from qualifications because they answer different questions. A
 * qualification is a licence to operate a class of equipment, usually issued
 * by an outside authority; training is what this mine required and delivered.
 * A site can insist on both, and they expire on different cycles.
 *
 * `category` drives the compliance rules (config/operators.php names the one
 * that counts as site induction), and `equipment_type` lets a machine-specific
 * competency be tied to the equipment it covers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operator_trainings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operator_id')->constrained()->cascadeOnDelete();

            $table->string('course');
            $table->string('category')->nullable();       // site_induction, safety, machine_competency, emergency, refresher
            $table->string('equipment_type')->nullable(); // set when the course covers specific equipment
            $table->string('provider')->nullable();
            $table->string('certificate_number')->nullable();

            $table->date('completed_on')->nullable();
            $table->date('expires_on')->nullable();

            // competent, in_progress, failed -- the assessment outcome, which
            // is not derivable from the dates.
            $table->string('competency')->default('competent');

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'expires_on']);
            $table->index(['operator_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operator_trainings');
    }
};
