<?php

use App\Jobs\ArchiveOldMetricsJob;
use App\Jobs\MachineIdleMonitoringJob;
use App\Jobs\PurgeExpiredSoftDeletesJob;
use App\Jobs\RouteSpeedMonitoringJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
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

// Dispatch a machine/metric sync for every connected manufacturer
// integration whose own configured interval has elapsed (Bell: 15 minutes,
// most others: 5 minutes) -- previously nothing scheduled this at all, for
// any of the 25 manufacturers; sync only ever happened via a manual
// "Sync Now" click or a direct API call.
Schedule::command('integrations:sync-due')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// Nightly: move machine_metrics rows older than the retention window
// (METRICS_RETENTION_DAYS, default 90) into machine_metrics_archive so the
// hot table -- the one every dashboard and analytics query hits -- stays
// lean. Archived telemetry remains queryable for historical reporting.
Schedule::job(new ArchiveOldMetricsJob)
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onOneServer();

// Weekly: permanently purge soft-deleted operational records once they are
// past the recovery grace period (SOFT_DELETE_RETENTION_DAYS, default 30).
Schedule::job(new PurgeExpiredSoftDeletesJob)
    ->weekly()
    ->sundays()
    ->at('03:00')
    ->withoutOverlapping()
    ->onOneServer();
