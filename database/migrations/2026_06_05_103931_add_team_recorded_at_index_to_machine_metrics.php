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
        Schema::table('machine_metrics', function (Blueprint $table) {
            // Composite index for time-range queries scoped to a team.
            // Supports: WHERE team_id = ? AND recorded_at BETWEEN ? AND ?
            // Critical for telemetry dashboards that query recent metrics per team.
            if (! $this->indexExists('machine_metrics', 'idx_metrics_team_recorded_at')) {
                $table->index(['team_id', 'recorded_at'], 'idx_metrics_team_recorded_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('machine_metrics', function (Blueprint $table) {
            $table->dropIndex('idx_metrics_team_recorded_at');
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $db = Schema::getConnection();
        $result = $db->select("SELECT name FROM sqlite_master WHERE type='index' AND name=?", [$index]);

        return count($result) > 0;
    }
};
