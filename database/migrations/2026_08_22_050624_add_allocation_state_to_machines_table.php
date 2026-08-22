<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * allocation_state is the ENTITLEMENT dimension, deliberately separate
 * from the operational `status` column (active/idle/maintenance/...):
 *
 *   occupying          - counts against the team's purchased/trial capacity
 *   pending_activation - discovered (e.g. by an OEM integration) but not
 *                        yet backed by an allocation; visible, not billable,
 *                        activatable once capacity exists
 *   released           - decommissioned; no longer consumes capacity
 *
 * Existing machines default to 'occupying': they are real, running assets
 * and must keep working (brief: never delete/disable on migration).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('machines', function (Blueprint $table) {
            $table->string('allocation_state', 30)->default('occupying')->index();
        });
    }

    public function down(): void
    {
        Schema::table('machines', function (Blueprint $table) {
            $table->dropColumn('allocation_state');
        });
    }
};
