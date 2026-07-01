<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Remove the three hardcoded Bell machines that were planted by the seeder
 * with fake "AFRICOAL-" equipment IDs. These are not real API machines and
 * must not appear on the fleet page alongside machines pulled from the live
 * Bell API via the self-service integration.
 *
 * Cleaned up:
 *   - bell_equipment rows with equipment_id LIKE 'AFRICOAL-%'
 *   - bell_equipment_daily_kpis linked to those rows
 *   - machines rows that had integration_id = NULL and external_id LIKE 'AFRICOAL-%'
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Find the equipment_keys for the fake seeder Bell equipment
        $fakeKeys = DB::table('bell_equipment')
            ->where('equipment_id', 'LIKE', 'AFRICOAL-%')
            ->pluck('equipment_key');

        if ($fakeKeys->isEmpty()) {
            return; // Already clean
        }

        // 2. Remove daily KPIs linked to the fake equipment
        DB::table('bell_equipment_daily_kpis')
            ->whereIn('equipment_key', $fakeKeys)
            ->delete();

        // 3. Remove caution codes, telemetry history, and current status
        foreach ([
            'bell_equipment_caution_codes',
            'bell_equipment_telemetry_history',
            'bell_equipment_current_status',
            'bell_equipment_location_history',
            'bell_equipment_fuel_usage_history',
            'bell_equipment_operating_hours_history',
            'bell_equipment_idle_hours_history',
            'bell_equipment_load_count_history',
            'bell_equipment_health_history',
        ] as $table) {
            DB::table($table)
                ->whereIn('equipment_key', $fakeKeys)
                ->delete();
        }

        // 4. Get the machine IDs that were linked to the fake equipment
        $linkedMachineIds = DB::table('bell_equipment')
            ->whereIn('equipment_key', $fakeKeys)
            ->whereNotNull('machine_id')
            ->pluck('machine_id');

        // 5. Delete the fake bell_equipment rows
        DB::table('bell_equipment')
            ->whereIn('equipment_key', $fakeKeys)
            ->delete();

        // 6. Delete the machines that had no real integration (integration_id IS NULL)
        //    and were carrying fake AFRICOAL- external IDs
        if ($linkedMachineIds->isNotEmpty()) {
            DB::table('machines')
                ->whereIn('id', $linkedMachineIds)
                ->whereNull('integration_id')
                ->where('external_id', 'LIKE', 'AFRICOAL-%')
                ->delete();
        }
    }

    public function down(): void
    {
        // Intentionally irreversible — seeder data should not be re-inserted
        // via rollback. Re-run the seeder if needed in a dev environment.
    }
};
