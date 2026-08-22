<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            // Written by IntegrationService::syncMachines() after every run:
            // duration, machines received, production-record delta, deep-sync
            // flag -- the numbers the System Health API panel reads (brief
            // §21: admins must be able to see why data stopped updating).
            $table->json('last_sync_stats')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->dropColumn('last_sync_stats');
        });
    }
};
