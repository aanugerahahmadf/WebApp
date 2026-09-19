<?php

return [
    /*
    | Supported runtime targets (see App\Enums\RuntimePlatform\RuntimePlatform):
    | - website_windows, website_macos, website_android, website_ios
    | - desktop_app_windows, desktop_app_macos (PWA standalone / NativePHP Desktop)
    | - mobile_app_android, mobile_app_ios (NativePHP Mobile)
    */
    'pwa' => [
        'enabled' => env('PLATFORM_PWA_ENABLED', true),
        'manifest_path' => '/manifest.webmanifest',
        'start_url' => env('PLATFORM_PWA_START_URL', '/user'),
        'theme_color' => env('PLATFORM_PWA_THEME_COLOR', '#fbbf24'),
        'background_color' => env('PLATFORM_PWA_BACKGROUND_COLOR', '#ffffff'),
    ],
];
