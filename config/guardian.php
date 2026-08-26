<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Guardian Service Token
    |--------------------------------------------------------------------------
    | Bearer token the Dot.Brain guardian presents to read /guardian/health.
    | The endpoint refuses to serve at all (503) when this is unset, so a
    | misconfigured deployment fails closed instead of exposing operational
    | detail. Rotate by changing the env value on both sides.
    */

    'token' => env('GUARDIAN_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Check Thresholds
    |--------------------------------------------------------------------------
    | Boundaries between healthy / warning / critical for each check. All
    | tunable per environment; the defaults suit the current production
    | shared-hosting profile (database queue drained from the scheduler).
    */

    'queue' => [
        'pending_warning' => (int) env('GUARDIAN_QUEUE_PENDING_WARNING', 100),
        'pending_critical' => (int) env('GUARDIAN_QUEUE_PENDING_CRITICAL', 500),
        'oldest_warning_seconds' => (int) env('GUARDIAN_QUEUE_OLDEST_WARNING', 300),
        'oldest_critical_seconds' => (int) env('GUARDIAN_QUEUE_OLDEST_CRITICAL', 900),
        'failed_warning' => (int) env('GUARDIAN_QUEUE_FAILED_WARNING', 5),
        'failed_critical' => (int) env('GUARDIAN_QUEUE_FAILED_CRITICAL', 20),
    ],

    'scheduler' => [
        'warning_seconds' => (int) env('GUARDIAN_SCHEDULER_WARNING', 300),
        'critical_seconds' => (int) env('GUARDIAN_SCHEDULER_CRITICAL', 900),
    ],

    'errors' => [
        'warning_per_hour' => (int) env('GUARDIAN_ERRORS_WARNING', 10),
        'critical_per_hour' => (int) env('GUARDIAN_ERRORS_CRITICAL', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Quiet Hours
    |--------------------------------------------------------------------------
    | Freshness only means something while machines are running. Quiet is
    | normally INFERRED from the fleet's own state (no machine active), which
    | needs no configuration and adapts to each team's shift pattern. Set an
    | explicit "HH:MM-HH:MM" window here only if you would rather state the
    | hours than have them inferred; it may cross midnight.
    */

    'quiet_hours' => [
        'window' => env('GUARDIAN_QUIET_HOURS'),
        'timezone' => env('GUARDIAN_QUIET_HOURS_TZ'),
    ],

    // Data-freshness checks express staleness as a multiple of each
    // integration's own sync interval: beyond 2x the data is late, beyond
    // 4x it has effectively stopped.
    'freshness' => [
        'warning_multiplier' => (float) env('GUARDIAN_FRESHNESS_WARNING_X', 2.0),
        'critical_multiplier' => (float) env('GUARDIAN_FRESHNESS_CRITICAL_X', 4.0),

        // Production rows legitimately update far less often than telemetry
        // (per-shift counters, quiet hours), so they get wall-clock
        // thresholds instead of sync-interval multiples.
        'production_warning_seconds' => (int) env('GUARDIAN_PRODUCTION_WARNING', 7200),
        'production_critical_seconds' => (int) env('GUARDIAN_PRODUCTION_CRITICAL', 21600),
    ],

];
