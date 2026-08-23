<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The people operating the fleet, as a first-class record.
 *
 * Deliberately NOT the users table. A user is someone who signs in to
 * Dot.Mines; an operator is someone who drives an ADT. Most operators are
 * never the first thing -- forcing an auth record (with an email address, a
 * password and 2FA) into existence for every driver on site would be both
 * wrong and a security liability. `user_id` is therefore optional, and links
 * the two only for the operators who do have a login.
 *
 * Employment, medical, qualification and training data live in their own
 * tables rather than as columns here, so the model stays readable and the
 * medical row can be permission-gated on its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();

            // Set null, not cascade: losing someone's login must not delete
            // the employment and compliance record attached to their name.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('employee_number');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('preferred_name')->nullable();

            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->string('emergency_contact_relationship')->nullable();

            $table->string('department')->nullable();
            $table->string('job_title')->nullable();
            $table->string('employment_type')->nullable(); // permanent, contract, temporary
            $table->date('employed_from')->nullable();
            $table->date('employed_until')->nullable();

            $table->foreignId('supervisor_id')->nullable()->constrained('operators')->nullOnDelete();
            $table->foreignId('mine_area_id')->nullable()->constrained()->nullOnDelete();
            $table->string('default_shift')->nullable(); // day, night

            /*
             * Employment state only. Whether someone is available, on shift,
             * or blocked by an expired licence is derived from assignments and
             * credentials rather than stored here -- a status column that can
             * disagree with the data behind it is worse than no column.
             */
            $table->string('employment_status')->default('active'); // active, leave, training, suspended, inactive

            $table->string('photo_path')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Employee numbers are unique within a mine, not globally.
            $table->unique(['team_id', 'employee_number']);
            $table->index(['team_id', 'employment_status']);
            $table->index(['team_id', 'last_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operators');
    }
};
