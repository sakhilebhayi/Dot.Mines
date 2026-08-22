<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rows created before the sync infrastructure existed carry a NULL
 * sync_version, which the delta query (`sync_version > cursor`) can never
 * match -- so pre-existing fleets, areas, notifications, and production
 * records would be invisible to every client forever. Stamp them with a
 * fresh sequence value per table so the first pull returns the full
 * initial snapshot (brief §8). Idempotent: only NULLs are touched.
 */
return new class extends Migration
{
    private const SYNCED_TABLES = ['machines', 'notifications', 'mine_areas', 'production_records'];

    public function up(): void
    {
        foreach (self::SYNCED_TABLES as $table) {
            $version = DB::table('sync_versions')->insertGetId(['created_at' => now()]);

            DB::table($table)->whereNull('sync_version')->update(['sync_version' => $version]);
        }
    }

    public function down(): void
    {
        // Intentionally irreversible: un-stamping would re-hide rows.
    }
};
