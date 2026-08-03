<?php

return [

    'paths' => [
        resource_path('views'),
    ],

    // Avoid realpath() — returns false when the directory doesn't yet exist
    'compiled' => env('VIEW_COMPILED_PATH', storage_path('framework/views')),

];
