<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for the CORS middleware. Controls which origins can access
    | the API. Both the Filament admin panel and Flutter mobile app
    | connect to this same backend.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'up'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    // `array_unique`/`array_filter` dipakai karena FRONTEND_URL dan ADMIN_URL
    // bisa menunjuk origin yang sama (kasus umum: satu domain untuk web, API,
    // dan panel admin). Tanpa itu domain itu muncul dua kali di header
    // Access-Control-Allow-Origin.
    'allowed_origins' => array_values(array_unique(array_filter([
        env('FRONTEND_URL', 'https://localhost'),
        env('ADMIN_URL', 'https://localhost'),
        'http://localhost:5173',
        'http://localhost:4200',
        'http://127.0.0.1:5173',
        'http://127.0.0.1:4200',
    ]))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'Origin', 'X-CSRF-TOKEN', 'X-Requested-With', 'X-LOCALE'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
