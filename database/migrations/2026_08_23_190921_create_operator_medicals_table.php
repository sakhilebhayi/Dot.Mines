<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Occupational medical fitness.
 *
 * Its own table, not columns on `operators`, for one reason above all: this
 * is health information about an identified person. Keeping it separate means
 * the read can be gated on its own permission, an operator record can be
 * loaded and displayed without it, and nothing has to remember to strip
 * medical columns out of a payload built for another purpose.
 *
 * `restrictions` is deliberately free text plus a flag rather than a fixed
 * list: a doctor writes "no night shift, no confined spaces" and any
 * enumeration we invent would either lose that or force it into the wrong box.
 * The flag is what the eligibility check reads; the text is what a human reads.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operator_medicals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operator_id')->constrained()->cascadeOnDelete();

            $table->string('certificate_number')->nullable();
            $table->string('provider')->nullable();
            $table->date('examined_on')->nullable();
            $table->date('expires_on')->nullable();

            // fit, fit_with_restrictions, temporarily_unfit, unfit
            $table->string('fitness')->default('fit');
            $table->boolean('has_restrictions')->default(false);
            $table->text('restrictions')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'expires_on']);
            $table->index('operator_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operator_medicals');
    }
};
