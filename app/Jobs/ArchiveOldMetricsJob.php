<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ArchiveOldMetricsJob
 *
 * Moves machine_metrics rows older than the configured retention window into
 * the machine_metrics_archive table, then deletes the originals.  This keeps
 * the hot table lean, improves query performance, and satisfies the 90-day
 * data-retention policy.
 *
 * Dispatch nightly via the scheduler:
 *   $schedule->job(new ArchiveOldMetricsJob)->dailyAt('02:00');
 *
 * Configuration (env):
 *   METRICS_RETENTION_DAYS  — days to keep in the hot table (default: 90)
 *   METRICS_ARCHIVE_BATCH   — rows per batch to avoid memory pressure (default: 5000)
 */
class ArchiveOldMetricsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        private readonly int $retentionDays = 0,
        private readonly int $batchSize = 0,
    ) {}

    public function handle(): void
    {
        $retentionDays = $this->retentionDays ?: (int) env('METRICS_RETENTION_DAYS', 90);
        $batchSize = $this->batchSize ?: (int) env('METRICS_ARCHIVE_BATCH', 5000);
        $cutoff = now()->subDays($retentionDays);

        Log::info('ArchiveOldMetricsJob: starting', [
            'cutoff' => $cutoff->toDateTimeString(),
            'retention_days' => $retentionDays,
            'batch_size' => $batchSize,
        ]);

        $totalArchived = 0;
        $totalDeleted = 0;

        do {
            // Fetch one batch of old IDs to archive
            $ids = DB::table('machine_metrics')
                ->where('created_at', '<', $cutoff)
                ->orderBy('id')
                ->limit($batchSize)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            // Copy to archive table (INSERT ... SELECT)
            DB::table('machine_metrics_archive')->insertUsing(
                [
                    'original_id', 'team_id', 'machine_id',
                    'latitude', 'longitude', 'speed', 'heading', 'altitude',
                    'engine_rpm', 'engine_temperature', 'coolant_temperature',
                    'oil_pressure', 'fuel_level', 'fuel_consumption_rate',
                    'throttle_position', 'battery_voltage', 'total_hours',
                    'idle_hours', 'load_weight', 'payload_capacity_used',
                    'tire_pressure_front_left', 'tire_pressure_front_right',
                    'tire_pressure_rear_left', 'tire_pressure_rear_right',
                    'raw_data', 'operating_hours', 'recorded_at',
                    'created_at', 'updated_at',
                ],
                DB::table('machine_metrics')
                    ->select([
                        'id as original_id', 'team_id', 'machine_id',
                        'latitude', 'longitude', 'speed', 'heading', 'altitude',
                        'engine_rpm', 'engine_temperature', 'coolant_temperature',
                        'oil_pressure', 'fuel_level', 'fuel_consumption_rate',
                        'throttle_position', 'battery_voltage', 'total_hours',
                        'idle_hours', 'load_weight', 'payload_capacity_used',
                        'tire_pressure_front_left', 'tire_pressure_front_right',
                        'tire_pressure_rear_left', 'tire_pressure_rear_right',
                        'raw_data', 'operating_hours', 'recorded_at',
                        'created_at', 'updated_at',
                    ])
                    ->whereIn('id', $ids)
            );

            $archived = $ids->count();
            $totalArchived += $archived;

            // Delete the archived rows from the hot table
            $deleted = DB::table('machine_metrics')->whereIn('id', $ids)->delete();
            $totalDeleted += $deleted;

            Log::debug('ArchiveOldMetricsJob: batch complete', [
                'archived' => $archived,
                'deleted' => $deleted,
            ]);
        } while ($ids->count() === $batchSize);

        Log::info('ArchiveOldMetricsJob: complete', [
            'total_archived' => $totalArchived,
            'total_deleted' => $totalDeleted,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ArchiveOldMetricsJob permanently failed', [
            'error' => $exception->getMessage(),
        ]);
    }
}
