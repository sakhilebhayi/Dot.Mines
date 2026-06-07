<?php

use App\Jobs\ArchiveOldMetricsJob;
use App\Jobs\CheckAIDriftJob;
use App\Jobs\MachineIdleMonitoringJob;
use App\Jobs\PurgeExpiredSoftDeletesJob;
use App\Jobs\PurgeOldAuditLogsJob;
use App\Jobs\PurgeOldFeedPostsJob;
use App\Jobs\RouteSpeedMonitoringJob;
use App\Jobs\SyncBellFleetDataJob;
use App\Jobs\SyncBellHistoricalDataJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    echo Inspiring::quote().PHP_EOL;
})->purpose('Display an inspiring quote');

// Schedule route speed monitoring job to run every 5 minutes
Schedule::job(new RouteSpeedMonitoringJob)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// Schedule machine idle monitoring job to run every 10 minutes
Schedule::job(new MachineIdleMonitoringJob)
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// Bell ISO15143-3 fleet data sync – every 15 minutes at :00, :15, :30, :45
Schedule::job(new SyncBellFleetDataJob)
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// Bell Fleetmatic REST API – historical telemetry backfill (location trail,
// fuel usage, operating hours, idle hours, load count) – runs every hour.
Schedule::job(new SyncBellHistoricalDataJob)
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

// Nightly metrics archival — moves records older than METRICS_RETENTION_DAYS (default: 90)
// from machine_metrics to machine_metrics_archive to keep the hot table lean.
Schedule::job(new ArchiveOldMetricsJob)
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onOneServer();

// Weekly: purge soft-deleted records past the grace period (default 30 days)
Schedule::job(new PurgeExpiredSoftDeletesJob)
    ->weekly()
    ->sundays()
    ->at('03:00')
    ->withoutOverlapping()
    ->onOneServer();

// Weekly: purge soft-deleted feed posts/comments past retention period (default 90 days)
Schedule::job(new PurgeOldFeedPostsJob)
    ->weekly()
    ->sundays()
    ->at('03:30')
    ->withoutOverlapping()
    ->onOneServer();

// Monthly: purge old audit log entries past retention period (default 365 days)
Schedule::job(new PurgeOldAuditLogsJob)
    ->monthly()
    ->withoutOverlapping()
    ->onOneServer();

// Weekly: AI drift detection — recalculate rolling 30-day accuracy for all AI agents
// Triggers notifications if accuracy drops below 70% (warn) or 60% (critical).
// Disables agents automatically if accuracy falls below 50%.
Schedule::job(new CheckAIDriftJob)
    ->weekly()
    ->sundays()
    ->at('04:00')
    ->withoutOverlapping()
    ->onOneServer();
