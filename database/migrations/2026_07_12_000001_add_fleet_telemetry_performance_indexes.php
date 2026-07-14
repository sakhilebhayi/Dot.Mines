<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add missing performance indexes for real-time fleet telemetry queries.
 *
 * Targets the hot paths used by:
 *   - MachineTelemetryService::forMachines()
 *   - Fleet::render() live status counts
 *   - LiveMap::getMachines()
 *   - ProductionDashboard OEM KPI queries
 *   - BellEquipmentLocationHistory speed/heading lookups
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── machines table ────────────────────────────────────────────────────
        // Fleet overview: filter by team + status, sort by name.
        $this->addIndexIfMissing('machines', ['team_id', 'status'], 'idx_machines_team_status');

        // Live map: filter by team + non-null location.
        $this->addIndexIfMissing('machines', ['team_id', 'last_location_latitude'], 'idx_machines_team_location');

        // Machine last-seen at for offline detection.
        $this->addIndexIfMissing('machines', ['team_id', 'last_seen_at'], 'idx_machines_team_last_seen');

        // ── bell_equipment table ───────────────────────────────────────────────
        // MachineTelemetryService: look up Bell equipment by machine_id.
        $this->addIndexIfMissing('bell_equipment', ['machine_id'], 'idx_bell_equip_machine_id');

        // ── bell_equipment_location_history table ─────────────────────────────
        // MachineTelemetryService::latestSpeedByEquipmentKey — already has
        // (equipment_key, recorded_at) composite index from migration
        // 2026_06_05_222644.  Add a DESC-ordered index for the ORDER BY DESC
        // query pattern used to find the latest location per machine.
        // (No-op if DB does not support index direction hints — SQLite ignores it.)
        $this->addIndexIfMissing(
            'bell_equipment_location_history',
            ['equipment_key', 'speed_kmh'],
            'idx_bell_loc_equip_speed',
        );

        // ── machine_metrics table ─────────────────────────────────────────────
        // Fastest lookup of latest metric per machine (used as telemetry fallback).
        $this->addIndexIfMissing('machine_metrics', ['machine_id', 'recorded_at'], 'idx_metrics_machine_recorded_at');

        // ── bell_equipment_daily_kpis table ───────────────────────────────────
        // SyncBellProductionRecordsJob: range query over equipment_key + date.
        // Unique index on (equipment_key, kpi_date) already exists from
        // 2026_06_04_182056.  Add machine-level path via bell_equipment.machine_id.
        // (Indirect — BellEquipment joins handle this; no additional index needed.)

        // ── production_records table ──────────────────────────────────────────
        // SyncBellProductionRecordsJob upsert lookup.
        $this->addIndexIfMissing(
            'production_records',
            ['team_id', 'machine_id', 'record_date', 'shift'],
            'idx_prod_records_team_machine_date_shift',
        );

        // ── alerts table ──────────────────────────────────────────────────────
        // Alert dashboard: filter active alerts by team.
        $this->addIndexIfMissing('alerts', ['team_id', 'status', 'created_at'], 'idx_alerts_team_status_created');
    }

    public function down(): void
    {
        $indexes = [
            'machines' => ['idx_machines_team_status', 'idx_machines_team_location', 'idx_machines_team_last_seen'],
            'bell_equipment' => ['idx_bell_equip_machine_id'],
            'bell_equipment_location_history' => ['idx_bell_loc_equip_speed'],
            'machine_metrics' => ['idx_metrics_machine_recorded_at'],
            'production_records' => ['idx_prod_records_team_machine_date_shift'],
            'alerts' => ['idx_alerts_team_status_created'],
        ];

        foreach ($indexes as $table => $names) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) use ($names): void {
                foreach ($names as $name) {
                    try {
                        $t->dropIndex($name);
                    } catch (Throwable) {
                        // Index may not exist — silently skip.
                    }
                }
            });
        }
    }

    /**
     * Add a composite index only if it does not already exist.
     *
     * @param  array<string>  $columns
     */
    private function addIndexIfMissing(string $table, array $columns, string $name): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        // SQLite-compatible check: query sqlite_master.
        $existing = DB::select(
            "SELECT name FROM sqlite_master WHERE type='index' AND name=?",
            [$name],
        );

        if (! empty($existing)) {
            return; // Already exists.
        }

        Schema::table($table, function (Blueprint $t) use ($columns, $name): void {
            $t->index($columns, $name);
        });
    }
};
