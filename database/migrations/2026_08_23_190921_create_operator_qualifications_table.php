<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What an operator is licensed to do -- and, specifically, what they are
 * licensed to DRIVE.
 *
 * The `equipment_type` column is what makes this more than a filing cabinet:
 * it names the equipment this licence authorises, in the same vocabulary the
 * fleet uses (see App\Support\EquipmentType), so the platform can answer
 * "may this person be assigned to that machine?" rather than only "does this
 * person have a licence somewhere?".
 *
 * A qualification that authorises no particular equipment (a first-aid
 * certificate, say) leaves equipment_type null and simply never satisfies a
 * machine requirement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operator_qualifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operator_id')->constrained()->cascadeOnDelete();

            $table->string('title');                       // "ADT Operator"
            $table->string('licence_number')->nullable();
            $table->string('equipment_category')->nullable(); // earthmoving, hauling, support
            $table->string('equipment_type')->nullable();     // EquipmentType::* -- what it authorises
            $table->string('issuing_authority')->nullable();

            $table->date('issued_on')->nullable();

            /*
             * Nullable: a few qualifications genuinely never expire, and
             * storing a fake far-future date to avoid a null would make
             * "expiring soon" reports lie.
             */
            $table->date('expires_on')->nullable();

            /*
             * Only states that cannot be derived from the dates. valid /
             * expiring / expired are computed from expires_on so they can
             * never disagree with it; suspended and revoked are decisions a
             * person made and must be stored.
             */
            $table->string('standing')->default('active'); // active, suspended, revoked

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'expires_on']);
            $table->index(['operator_id', 'equipment_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operator_qualifications');
    }
};
