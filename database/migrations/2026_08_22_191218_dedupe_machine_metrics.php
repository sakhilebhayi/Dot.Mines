<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Operational data P6 (brief §19): while a machine is parked, every
 * 15-minute sync re-stored the provider's unchanged reading -- 78% of all
 * machine_metrics rows were exact (machine_id, recorded_at) duplicates in
 * production. The sync paths now skip already-stored readings; this
 * removes the rows they left behind, keeping the earliest of each group.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            DELETE FROM machine_metrics
            WHERE recorded_at IS NOT NULL
              AND id NOT IN (
                SELECT keep_id FROM (
                    SELECT MIN(id) AS keep_id
                    FROM machine_metrics
                    WHERE recorded_at IS NOT NULL
                    GROUP BY machine_id, recorded_at
                ) AS keepers
              )
        SQL);
    }

    public function down(): void
    {
        // Duplicate rows carry no information; there is nothing to restore.
    }
};
