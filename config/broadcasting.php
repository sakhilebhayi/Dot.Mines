<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    |
    | This option defines the default broadcaster that will be used by the
    | framework when an event needs to be broadcast. You may set this to
    | any of the connections defined in the "connections" array below.
    |
    */

    /*
     * Laravel 12 key first, legacy key second, and a SAFE resting default:
     * 'null'. The old 'pusher' fallback meant hosts without broadcast env
     * vars silently queued real Pusher API calls with empty credentials.
     */
    'default' => env('BROADCAST_CONNECTION', env('BROADCAST_DRIVER', 'null')),

    /*
    |--------------------------------------------------------------------------
    | Broadcast Connections
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the broadcast connections that will be used
    | to broadcast events to other parts of your application. A sample
    | configuration is provided for every driver that is supported by Laravel.
    |
    */

    'connections' => [
        'reverb' => [
            'driver' => 'reverb',
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
            // This is the PHP backend's own outbound path for publishing
            // events into Reverb (POST /apps/{id}/events) -- deliberately
            // the internal REVERB_SERVER_HOST/PORT the reverb:start process
            // actually binds to (loopback-only in production), not the
            // public REVERB_HOST/PORT browsers connect through. Publishing
            // straight to the internal port skips an unnecessary round trip
            // out through the reverse proxy and back, and keeps the
            // server-to-server /apps/* API off the public internet
            // entirely -- only /app/{key} (the browser's path) is proxied.
            // Falls back to the public values when *_SERVER_* isn't set
            // (e.g. local dev, where there's no reverse proxy at all).
            'options' => [
                'host' => env('REVERB_SERVER_HOST', env('REVERB_HOST', 'localhost')),
                'port' => env('REVERB_SERVER_PORT', env('REVERB_PORT', 8080)),
                'scheme' => env('REVERB_SERVER_HOST') ? 'http' : env('REVERB_SCHEME', 'http'),
                'useTLS' => env('REVERB_SERVER_HOST') ? false : env('REVERB_SCHEME') === 'https',
            ],
            'client_options' => [
                // Optionally, provide additional client options
                // 'scheme' => 'https',
            ],
        ],

        /*
         * Managed Pusher Channels (hybrid Slice 3 enablement): production's
         * push transport, since shared hosting cannot run a Reverb process.
         * Server-side publishing + private-channel auth signing; the browser
         * counterpart is keyed by VITE_PUSHER_APP_KEY at build time.
         */
        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'app_id' => env('PUSHER_APP_ID'),
            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER'),
                'host' => env('PUSHER_HOST') ?: 'api-'.env('PUSHER_APP_CLUSTER', 'mt1').'.pusher.com',
                'port' => env('PUSHER_PORT', 443),
                'scheme' => env('PUSHER_SCHEME', 'https'),
                'encrypted' => true,
                'useTLS' => env('PUSHER_SCHEME', 'https') === 'https',
            ],
            'client_options' => [
                // Guzzle client options: https://docs.guzzlephp.org/en/stable/request-options.html
            ],
        ],

        'ably' => [
            'driver' => 'ably',
            'key' => env('ABLY_KEY'),
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],
    ],

];
