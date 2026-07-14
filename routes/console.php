<?php

use App\Jobs\ArchiveOldMetricsJob;
use App\Jobs\CheckAIDriftJob;
use App\Jobs\DispatchIntegrationSyncsJob;
use App\Jobs\MachineIdleMonitoringJob;
use App\Jobs\PurgeExpiredSoftDeletesJob;
use App\Jobs\PurgeOldAuditLogsJob;
use App\Jobs\PurgeOldFeedPostsJob;
use App\Jobs\RouteSpeedMonitoringJob;
use App\Jobs\SyncBellEngineConditionJob;
use App\Jobs\SyncBellFleetDataJob;
use App\Jobs\SyncBellFuelJob;
use App\Jobs\SyncBellHistoricalDataJob;
use App\Jobs\SyncBellLocationsJob;
use App\Jobs\SyncBellOperatingHoursJob;
use App\Jobs\SyncBellPayloadJob;
use App\Jobs\SyncBellProductionRecordsJob;
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

// ── Self-service integration sync pipeline ─────────────────────────────────
// Dispatches SyncIntegrationJob for every 'connected' integration that is
// due for a sync, respecting each integration's configured sync_frequency.
Schedule::job(new DispatchIntegrationSyncsJob)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// Schedule machine idle monitoring job to run every 10 minutes
Schedule::job(new MachineIdleMonitoringJob)
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// Bell ISO15143-3 fleet data sync – every 5 minutes
Schedule::job(new SyncBellFleetDataJob)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// Bell Fleetmatic REST API – historical telemetry (all 13 signals) – every 5 minutes
Schedule::job(new SyncBellHistoricalDataJob)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// ── Bell per-signal granular jobs ─────────────────────────────────────────
// BELL_LOCATION_POLL_SECONDS controls the location fetch frequency.
//
// Intervals >= 60 s are handled directly by the Laravel scheduler below.
// Intervals <  60 s require the bell:watch-locations artisan command running
// under Supervisor (identical to a queue worker).  A once-per-minute safety-net
// is registered for environments without Supervisor so location data is never
// completely stalled.
$bellLocationInterval = (int) config('integrations.bell_polling.location_interval_seconds', 300);

if ($bellLocationInterval >= 300) {
    Schedule::job(new SyncBellLocationsJob)
        ->everyFiveMinutes()
        ->withoutOverlapping()
        ->onOneServer();
} elseif ($bellLocationInterval >= 120) {
    Schedule::job(new SyncBellLocationsJob)
        ->everyTwoMinutes()
        ->withoutOverlapping()
        ->onOneServer();
} elseif ($bellLocationInterval >= 60) {
    Schedule::job(new SyncBellLocationsJob)
        ->everyMinute()
        ->withoutOverlapping()
        ->onOneServer();
} else {
    // Sub-minute: bell:watch-locations (Supervisor) is the primary mechanism.
    // Register a per-minute safety net so the scheduler keeps data flowing
    // even if the persistent command is not yet deployed.
    Schedule::command('bell:watch-locations --once')
        ->everyMinute()
        ->withoutOverlapping()
        ->onOneServer();
}

Schedule::job(new SyncBellEngineConditionJob)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::job(new SyncBellPayloadJob)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::job(new SyncBellFuelJob)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::job(new SyncBellOperatingHoursJob)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// Nightly metrics archival — moves records older than METRICS_RETENTION_DAYS (default: 90)
// from machine_metrics to machine_metrics_archive to keep the hot table lean.
Schedule::job(new ArchiveOldMetricsJob)
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onOneServer();

// Nightly: sync Bell OEM daily KPI data into ProductionRecord rows so the
// production dashboard shows real data without manual entry.
// Runs just after midnight to capture the full previous day's production.
Schedule::job(new SyncBellProductionRecordsJob(lookbackDays: 7))
    ->dailyAt('00:30')
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
