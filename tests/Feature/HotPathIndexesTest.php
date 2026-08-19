<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Readiness slice 5 (§21): the hottest query shapes must be index-backed.
 * machine_metrics previously had no recorded_at index at all while every
 * telemetry consumer range-scans machine_id/team_id + recorded_at.
 */
class HotPathIndexesTest extends TestCase
{
    use RefreshDatabase;

    private function indexNames(string $table): array
    {
        return array_column(Schema::getIndexes($table), 'name');
    }

    public function test_telemetry_range_scans_are_index_backed(): void
    {
        $indexes = $this->indexNames('machine_metrics');

        $this->assertContains('idx_metrics_machine_recorded', $indexes);
        $this->assertContains('idx_metrics_team_recorded', $indexes);
    }

    public function test_production_notification_and_alert_hot_paths_are_index_backed(): void
    {
        $this->assertContains('idx_production_team_date', $this->indexNames('production_records'));
        $this->assertContains('idx_notifications_team_created', $this->indexNames('notifications'));
        $this->assertContains('idx_alerts_team_created', $this->indexNames('alerts'));
    }
}
