<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Sentry Configuration
    |--------------------------------------------------------------------------
    | Minimal configuration used by the application. The `sentry/sentry-laravel`
    | package will also read these environment variables if the package is
    | installed. Adjust values in your environment or in deploy pipeline.
    */

    'dsn' => env('SENTRY_DSN', null),
    'environment' => env('SENTRY_ENVIRONMENT', env('APP_ENV', 'production')),
    'release' => env('SENTRY_RELEASE', null),
    'traces_sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 0.0),
];
