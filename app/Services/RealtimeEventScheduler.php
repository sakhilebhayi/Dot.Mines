<?php

namespace App\Services;

use App\Jobs\AlertGenerationJob;
use App\Jobs\GeofenceCrossingDetectionJob;
use App\Jobs\MachineLocationUpdateJob;
use App\Jobs\MachineStatusMonitoringJob;
use App\Models\Integration;
use App\Models\Team;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;

class RealtimeEventScheduler
{
    /**
     * Register the real-time event jobs with the scheduler.
     *
     * This service is called from AppServiceProvider to schedule all
     * the jobs that power the real-time update system:
     *
     * - Machine location updates (every 5 minutes -- API-bound)
     * - Alert generation (every 30 seconds -- database-only)
     * - Geofence crossing detection (every 30 seconds -- database-only)
     * - Machine status monitoring (every 5 minutes -- API-bound)
     *
     * All jobs queue independently and are non-blocking.
     */
    public static function register(Schedule $schedule): void
    {
        // Location updates: every five minutes. This ran every TEN SECONDS
        // -- a Reverb-era relic that dispatched an API-polling job per
        // integration 360 times an hour into queues nothing drained, and
        // (once drained) hammered a provider whose data only changes every
        // 15 minutes. Bell throttled the server IP for exactly this on
        // 2026-08-21. Five minutes still beats the 15-minute full sync for
        // position updates without abusing anyone's API.
        $schedule->call(function () {
            self::scheduleLocationUpdates();
        })->everyFiveMinutes()
            ->name('schedule-location-updates')
            ->onOneServer();

        // Alert generation: Every 30 seconds to catch new conditions
        $schedule->call(function () {
            self::scheduleAlertGeneration();
        })->everyThirtySeconds()
            ->name('schedule-alert-generation')
            ->onOneServer();

        // Geofence detection: Every 30 seconds for responsive geofencing
        $schedule->call(function () {
            self::scheduleGeofenceDetection();
        })->everyThirtySeconds()
            ->name('schedule-geofence-detection')
            ->onOneServer();

        // Status monitoring: every five minutes, same reasoning (and same
        // incident) as the location schedule above -- connectivity is
        // derived from the same provider snapshot.
        $schedule->call(function () {
            self::scheduleStatusMonitoring();
        })->everyFiveMinutes()
            ->name('schedule-status-monitoring')
            ->onOneServer();
    }

    /**
     * Schedule machine location update jobs for all connected integrations.
     */
    private static function scheduleLocationUpdates(): void
    {
        try {
            $integrations = Integration::where('status', 'connected')
                ->get();

            foreach ($integrations as $integration) {
                MachineLocationUpdateJob::dispatch($integration)
                    ->onQueue('locations');
            }

            if ($integrations->count() > 0) {
                Log::debug('Scheduled location update jobs', [
                    'count' => $integrations->count(),
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to schedule location updates', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Schedule alert generation jobs for all teams.
     */
    private static function scheduleAlertGeneration(): void
    {
        try {
            $teams = Team::where('status', 'active')
                ->has('machines', '>', 0)
                ->get();

            foreach ($teams as $team) {
                AlertGenerationJob::dispatch($team)
                    ->onQueue('alerts');
            }

            if ($teams->count() > 0) {
                Log::debug('Scheduled alert generation jobs', [
                    'count' => $teams->count(),
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to schedule alert generation', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Schedule geofence crossing detection jobs for all teams.
     */
    private static function scheduleGeofenceDetection(): void
    {
        try {
            $teams = Team::where('status', 'active')
                ->has('geofences', '>', 0)
                ->has('machines', '>', 0)
                ->get();

            foreach ($teams as $team) {
                GeofenceCrossingDetectionJob::dispatch($team)
                    ->onQueue('geofences');
            }

            if ($teams->count() > 0) {
                Log::debug('Scheduled geofence detection jobs', [
                    'count' => $teams->count(),
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to schedule geofence detection', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Schedule machine status monitoring jobs for all connected integrations.
     */
    private static function scheduleStatusMonitoring(): void
    {
        try {
            $integrations = Integration::where('status', 'connected')
                ->get();

            foreach ($integrations as $integration) {
                MachineStatusMonitoringJob::dispatch($integration)
                    ->onQueue('status');
            }

            if ($integrations->count() > 0) {
                Log::debug('Scheduled status monitoring jobs', [
                    'count' => $integrations->count(),
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to schedule status monitoring', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
