<?php

use App\Jobs\MachineIdleMonitoringJob;
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
