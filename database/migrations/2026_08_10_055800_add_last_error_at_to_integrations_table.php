<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MachineLocationUpdateJob::failed() has been writing 'last_error_at' on
 * every permanent job failure since it was introduced -- the column never
 * existed, and the field isn't in Integration::$fillable either, so the
 * write was silently dropped both ways (mass-assignment protection, then
 * it would have been a missing-column SQL error if it had gotten that far).
 * Real column now exists, matching the intent: when did the last error
 * actually happen, distinct from last_sync_at (when did a sync last
 * succeed) -- both are needed for real integration health surfacing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->timestamp('last_error_at')->nullable()->after('last_error');
        });
    }

    public function down(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->dropColumn('last_error_at');
        });
    }
};
