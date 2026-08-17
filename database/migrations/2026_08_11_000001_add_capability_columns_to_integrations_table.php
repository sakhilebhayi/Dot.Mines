<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * capabilities: the data streams (subset of fleet/telemetry/production/
 * location) this integration's account has actually been observed to
 * provide, derived from a real API response by
 * IntegrationService::deriveCapabilities() -- never hardcoded per provider.
 *
 * sync_streams: per-stream status ({status, last_synced_at, records} per
 * capability key), what powers "Fleet sync: Active / Production sync:
 * Active" in the UI instead of one bundled status/last_sync_at pair.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->json('capabilities')->nullable()->after('config');
            $table->json('sync_streams')->nullable()->after('capabilities');
        });
    }

    public function down(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->dropColumn(['capabilities', 'sync_streams']);
        });
    }
};
