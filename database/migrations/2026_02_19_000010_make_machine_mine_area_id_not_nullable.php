<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('machines', 'mine_area_id')) {
            // If column doesn't exist, nothing to do here.
            return;
        }

        // For teams that have active mine areas, assign machines with NULL mine_area_id to a sensible default (first active area)
        $teams = DB::table('mine_areas')
            ->select('team_id', DB::raw('MIN(id) as area_id'))
            ->where('status', 'active')
            ->groupBy('team_id')
            ->get();

        foreach ($teams as $t) {
            try {
                DB::table('machines')
                    ->where('team_id', $t->team_id)
                    ->whereNull('mine_area_id')
                    ->update(['mine_area_id' => $t->area_id]);
            } catch (Exception $e) {
                // ignore failures for specific teams
            }
        }

        // Determine driver and attempt to alter column nullability where supported
        try {
            $driver = DB::getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (Exception $e) {
            $driver = null;
        }

        if ($driver === 'sqlite' || $driver === null) {
            // SQLite does not support altering column nullability easily; skip to avoid corrupting DB.
            return;
        }

        try {
            if ($driver === 'mysql') {
                DB::statement('ALTER TABLE `machines` MODIFY `mine_area_id` BIGINT UNSIGNED NOT NULL');
            } elseif ($driver === 'pgsql') {
                DB::statement('ALTER TABLE machines ALTER COLUMN mine_area_id SET NOT NULL');
            } else {
                // Fallback: try a generic SQL ALTER (may work on some drivers)
                DB::statement('ALTER TABLE machines ALTER COLUMN mine_area_id SET NOT NULL');
            }
        } catch (Exception $e) {
            // If altering fails, don't break migrations; the model-level validation still protects new writes.
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('machines', 'mine_area_id')) {
            return;
        }

        try {
            $driver = DB::getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (Exception $e) {
            $driver = null;
        }

        if ($driver === 'sqlite' || $driver === null) {
            return;
        }

        try {
            if ($driver === 'mysql') {
                DB::statement('ALTER TABLE `machines` MODIFY `mine_area_id` BIGINT UNSIGNED NULL');
            } elseif ($driver === 'pgsql') {
                DB::statement('ALTER TABLE machines ALTER COLUMN mine_area_id DROP NOT NULL');
            } else {
                DB::statement('ALTER TABLE machines ALTER COLUMN mine_area_id DROP NOT NULL');
            }
        } catch (Exception $e) {
            // ignore
        }
    }
};
