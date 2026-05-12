<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | WhatsApp Web fetches public preview URLs cross-origin from the browser.
    | These routes are intentionally public, so we expose them for simple GET
    | requests and let Laravel answer with the proper CORS headers.
    |
    */

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'pixel/*',
        'pix/*',
        'noticia/*',
        'intimacao/*',
        'tracker/*',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
