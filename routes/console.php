<?php

use App\Jobs\MachineIdleMonitoringJob;
use App\Jobs\RouteSpeedMonitoringJob;
use App\Jobs\SyncBellFleetDataJob;
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

// Bell ISO15143-3 fleet data sync – every 15 minutes at :00, :15, :30, :45
Schedule::job(new SyncBellFleetDataJob)
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onOneServer();
