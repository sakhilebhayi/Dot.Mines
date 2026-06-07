<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        // Supported values including the new dismissed_unresolved state
        $allowed = ['active', 'acknowledged', 'resolved', 'dismissed', 'dismissed_unresolved'];
        $list = "('".implode("','", $allowed)."')";

        // Skip for SQLite (no reliable check constraint support across versions)
        if ($driver === 'sqlite') {
            return;
        }

        // Add check constraint for Postgres and MySQL (MySQL 8+ supports CHECK)
        try {
            if ($driver === 'pgsql') {
                DB::statement("ALTER TABLE alerts ADD CONSTRAINT chk_alert_status_values CHECK (status IN {$list})");
            } else {
                // MySQL and others
                DB::statement("ALTER TABLE alerts ADD CONSTRAINT chk_alert_status_values CHECK (status IN {$list})");
            }
        } catch (Exception $e) {
            // Log and continue - DB may not support adding a constraint at runtime
            Log::warning('Could not add alerts status check constraint: '.$e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        try {
            if ($driver === 'pgsql') {
                DB::statement('ALTER TABLE alerts DROP CONSTRAINT IF EXISTS chk_alert_status_values');
            } else {
                // MySQL: DROP CHECK constraint
                DB::statement('ALTER TABLE alerts DROP CHECK IF EXISTS chk_alert_status_values');
            }
        } catch (Exception $e) {
            Log::warning('Could not drop alerts status check constraint: '.$e->getMessage());
        }
    }
};
