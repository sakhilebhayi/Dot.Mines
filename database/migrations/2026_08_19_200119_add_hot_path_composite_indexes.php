<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Composite indexes for the hottest query shapes (readiness slice 5,
     * §21 scale review). machine_metrics had NO recorded_at index at all,
     * yet every consumer -- MachinePerformanceService day windows, the
     * utilization report, fuel daily metrics, ArchiveOldMetricsJob -- scans
     * machine_id/team_id + recorded_at ranges. At telemetry volume (a
     * reading every 5-15 minutes per machine) those become full-table
     * scans without these.
     */
    public function up(): void
    {
        Schema::table('machine_metrics', function (Blueprint $table) {
            $table->index(['machine_id', 'recorded_at'], 'idx_metrics_machine_recorded');
            $table->index(['team_id', 'recorded_at'], 'idx_metrics_team_recorded');
        });

        Schema::table('production_records', function (Blueprint $table) {
            // Production dashboard + reports filter team_id with a
            // record_date range on every load.
            $table->index(['team_id', 'record_date'], 'idx_production_team_date');
        });

        Schema::table('notifications', function (Blueprint $table) {
            // The bell reads latest-N per team on every page render.
            $table->index(['team_id', 'created_at'], 'idx_notifications_team_created');
        });

        Schema::table('alerts', function (Blueprint $table) {
            // Dashboard recent-alerts: latest-N per team by created_at
            // (idx_alerts_team_status covers the status counts, not this).
            $table->index(['team_id', 'created_at'], 'idx_alerts_team_created');
        });
    }

    public function down(): void
    {
        Schema::table('machine_metrics', function (Blueprint $table) {
            $table->dropIndex('idx_metrics_machine_recorded');
            $table->dropIndex('idx_metrics_team_recorded');
        });

        Schema::table('production_records', function (Blueprint $table) {
            $table->dropIndex('idx_production_team_date');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('idx_notifications_team_created');
        });

        Schema::table('alerts', function (Blueprint $table) {
            $table->dropIndex('idx_alerts_team_created');
        });
    }
};
