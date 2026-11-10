<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Capacitor Applications
    |--------------------------------------------------------------------------
    |
    | Konfigurasi untuk setiap shell/aplikasi Capacitor di project ini.
    | Nilai `server.url` di sini adalah referensi sisi Laravel; sumber
    | kebenaran yang dibaca aplikasi native ada di `capacitor.config.json`
    | dan `src/js/launcher.js` masing-masing app (diubah via
    | `npm run set:url -- <url>` di direktori app terkait).
    |
    */

    'apps' => [

        'application' => [
            'name' => 'Dekorasi User',
            'app_id' => 'id.dekorasi.pengantin.user',
            'path' => 'app/Capacitor/Application',
            'web_dir' => 'dist',
            'server' => [
                'url' => env('CAPACITOR_APPLICATION_URL', 'http://127.0.0.1:8000/home'),
                'android_scheme' => env('CAPACITOR_APPLICATION_ANDROID_SCHEME', 'http'),
                'ios_scheme' => env('CAPACITOR_APPLICATION_IOS_SCHEME', 'http'),
                'cleartext' => (bool) env('CAPACITOR_APPLICATION_CLEARTEXT', true),
            ],
        ],

        'desktop_admin' => [
            'name' => 'Dekorasi Admin',
            'app_id' => 'id.dekorasi.pengantin.admin',
            'path' => 'app/Capacitor/DekstopAdminApp',
            'web_dir' => 'dist',
            'server' => [
                'url' => env('CAPACITOR_DESKTOP_ADMIN_URL', 'http://127.0.0.1:8000/admin'),
                'android_scheme' => env('CAPACITOR_DESKTOP_ADMIN_ANDROID_SCHEME', 'http'),
                'ios_scheme' => env('CAPACITOR_DESKTOP_ADMIN_IOS_SCHEME', 'http'),
                'cleartext' => (bool) env('CAPACITOR_DESKTOP_ADMIN_CLEARTEXT', true),
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Default App
    |--------------------------------------------------------------------------
    */

    'default' => env('CAPACITOR_DEFAULT_APP', 'application'),

];
