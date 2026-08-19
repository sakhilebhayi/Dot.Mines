<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Published to replace the framework default of allowed_origins: ['*'],
    | which let any website's JavaScript call the API. The app is served
    | same-origin (Livewire + Sanctum), so the only origin that ever needs
    | CORS access is the app itself; add explicit extra origins via the
    | comma-separated CORS_ALLOWED_ORIGINS env when a separate frontend or
    | tool legitimately needs one. Never use '*' for this API.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', env('APP_URL', 'http://localhost')))
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
