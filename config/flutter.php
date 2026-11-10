<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Flutter Application
    |--------------------------------------------------------------------------
    |
    | Konfigurasi referensi untuk aplikasi Flutter/mobile (jika digunakan)
    | dan tooling terkait di project ini.
    |
    */

    'app' => [
        'name' => env('FLUTTER_APP_NAME', env('APP_NAME', 'Wedding Flower Decorations')),
        'path' => env('FLUTTER_APP_PATH', 'D:\\WeddingFlowersDecorationApp\\mobile_app'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Localization (ARB)
    |--------------------------------------------------------------------------
    */

    'l10n' => [
        'path' => env('FLUTTER_L10N_PATH', 'D:\\WeddingFlowersDecorationApp\\mobile_app\\lib\\l10n'),
        'template_arb' => env('FLUTTER_L10N_TEMPLATE', 'app_id.arb'),
    ],

    /*
    |--------------------------------------------------------------------------
    | API / Backend
    |--------------------------------------------------------------------------
    |
    | Base URL backend yang digunakan aplikasi Flutter untuk mengakses API
    | Laravel. Sesuaikan untuk device fisik (IP LAN / adb reverse) atau
    | environment deployment.
    |
    */

    'api' => [
        'base_url' => env('FLUTTER_API_BASE_URL', 'http://127.0.0.1:8000'),
        'prefix' => env('FLUTTER_API_PREFIX', '/api'),
    ],

];
