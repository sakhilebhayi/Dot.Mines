<?php

use App\Jobs\ArchiveOldMetricsJob;
use App\Jobs\MachineIdleMonitoringJob;
use App\Jobs\PurgeExpiredSoftDeletesJob;
use App\Jobs\RouteSpeedMonitoringJob;
use App\Models\Team;
use App\Services\Feed\FeedProductionAggregator;
use App\Services\Guardian\SchedulerHeartbeat;
use App\Services\Sync\SyncSequence;
use Illuminate\Support\Facades\Schedule;

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

// Drain the database queue from the scheduler's cron tick. Shared hosting
// has no systemd/supervisor for a resident queue worker, and the sync
// driver runs "background" jobs inline in whatever process dispatched them
// -- which is how a manual integration sync (dozens of sequential Bell API
// calls) ran inside a web request until the gateway killed it with a
// Request Timeout. With QUEUE_CONNECTION=database, dispatches land in the
// jobs table and this drains them within a minute, off the web request.
// --max-time keeps each drain inside the minute so ticks never stack up.
// --queue must list EVERY named queue jobs dispatch to: a bare queue:work
// drains only "default", and 996 location/status/monitoring jobs silently
// piled up on 2026-08-21 while their queues went unserviced. Default
// first: user-triggered work (syncs, reports) beats background polling.
Schedule::command('queue:work --queue=default,locations,status,monitoring,alerts,geofences,notifications,webhooks --stop-when-empty --max-time=50 --tries=1')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground()
    ->onOneServer()
    ->when(fn (): bool => config('queue.default') === 'database');

// Dispatch a machine/metric sync for every connected manufacturer
// integration whose own configured interval has elapsed (Bell: 15 minutes,
// most others: 5 minutes) -- previously nothing scheduled this at all, for
// any of the 25 manufacturers; sync only ever happened via a manual
// "Sync Now" click or a direct API call.
Schedule::command('integrations:sync-due')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// Hourly production line for the Mine Operations Feed: the delta of the
// fleet's load counters since the previous run, computed by the SAME
// snapshot service the Production page reads. Publishes nothing on quiet
// hours, nothing across the midnight counter reset, and nothing when
// telemetry is stale -- the aggregator itself enforces all three.
Schedule::call(function (): void {
    foreach (Team::query()->get() as $team) {
        app(FeedProductionAggregator::class)->publishHourly($team);
    }
})->name('feed-production-hourly')
    ->hourlyAt(5)
    ->withoutOverlapping()
    ->onOneServer();

// Daily operator-compliance sweep: warn admins 30/14/7 days before a
// licence, medical or training certificate lapses, and once when it has.
// The service dedupes on (credential, milestone), so running this daily --
// or re-running it after a failure -- never repeats an alert.
Schedule::command('operators:check-compliance')
    ->dailyAt('05:30')
    ->withoutOverlapping()
    ->onOneServer();

// Daily, after the previous day's telemetry is fully synced: raise
// potential production-loss events (machine reporting but engine-hours
// meter not moving) as pending_classification for human review on the
// Machine Details page. Detection never auto-confirms a loss.
Schedule::command('production:detect-losses')
    ->dailyAt('01:30')
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

// Nightly: prune the sync_versions sequence table (each row's id is a
// handed-out version number; only the tail matters for SyncSequence::current).
Schedule::call(fn () => SyncSequence::prune())
    ->name('sync-sequence-prune')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->onOneServer();

// Guardian scheduler heartbeat: proof that cron ticks are firing at all.
// SchedulerCheck (served at /guardian/health) reads the beat's age; if it
// goes stale the Dot.Brain guardian knows every scheduled sync above has
// silently stopped, even though the web app still serves pages. Every
// minute, no overlap guard needed -- a cache put is idempotent and cheap.
Schedule::call(fn () => SchedulerHeartbeat::beat())
    ->name('guardian-scheduler-heartbeat')
    ->everyMinute();
