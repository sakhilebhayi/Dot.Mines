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
        $tables = [
            'bell_distance_travelled',
            'bell_payload_totals',
            'bell_def_levels',
            'bell_fuel_levels',
            'bell_regeneration_hours',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                // Prevent duplicate telemetry rows for the same machine snapshot.
                // snapshot_time is nullable so duplicates where both are NULL are
                // allowed (Bell API offline records) — the UNIQUE constraint only
                // fires when snapshot_time carries a real timestamp value.
                $blueprint->unique(['equipment_key', 'snapshot_time'], substr($blueprint->getTable(), 0, 20).'_equip_snap_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'bell_distance_travelled',
            'bell_payload_totals',
            'bell_def_levels',
            'bell_fuel_levels',
            'bell_regeneration_hours',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropUnique(substr($blueprint->getTable(), 0, 20).'_equip_snap_unique');
            });
        }
    }
};
