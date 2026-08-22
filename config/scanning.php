<?php

return [
    // Enable or disable server-side virus scanning of uploaded files.
    // This should be enabled only when a trusted scanner (clamd/clamscan) is
    // available on the host or via a sidecar and you accept the additional
    // operational risk of launching a subprocess.
    'virus' => [
        'enabled' => env('VIRUS_SCAN_ENABLED', false),
    ],

    /*
     * clamd connection: prefer a unix socket; else TCP host/port. Defined
     * here (not as env() fallbacks in the service) so config:cache works.
     */
    'clamav' => [
        'socket' => env('CLAMD_SOCKET', ''),
        'host' => env('CLAMD_HOST', '127.0.0.1'),
        'port' => (int) env('CLAMD_PORT', 3310),
    ],
];
