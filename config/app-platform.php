<?php

/*
 * Set item bottom-nav untuk storefront.
 *
 * Panel 'user' sudah dilebur ke panel 'welcome', tapi set itemnya tetap
 * disimpan DUA nama: 'welcome' (panel yang benar-benar merender bar) dan
 * 'user' (nama lama, masih ditanyakan beberapa pemanggil).
 *
 * Satu array untuk dua nama. Kalau ini ditulis dua kali, cepat atau lambat
 * keduanya jadi berbeda -- dan bar hilang dari satu panel tanpa error.
 */
$storefrontNav = [
    [
        'label' => 'Beranda',
        'icon' => '/images/icons/home.svg',
        'url' => \App\Filament\Welcome\Pages\Home\Home::class,
    ],
    [
        'label' => 'Cari',
        'icon' => '/images/icons/search.svg',
        'url' => \App\Filament\Welcome\Pages\CbirSearchPage\CbirSearchPage::class,
    ],
    [
        'label' => 'Pesanan',
        'icon' => '/images/icons/clipboard.svg',
        'url' => \App\Filament\Welcome\Resources\HistoryResource\HistoryResource::class,
        'match' => 'starts',
    ],
    [
        'label' => 'Pesan',
        'icon' => '/images/icons/chat.svg',
        'url' => \App\Filament\Welcome\Pages\MessagesPage\MessagesPage::class,
        'match' => 'starts',
    ],
    [
        'label' => 'Profil',
        'icon' => '/images/icons/user_circle.svg',
        'url' => \App\Filament\Welcome\Pages\EditProfilePage\EditProfilePage::class,
    ],
];

return [

    /*
    |--------------------------------------------------------------------------
    | Forced Runtime Platform
    |--------------------------------------------------------------------------
    |
    | Pin the runtime platform for every request, bypassing User-Agent and
    | cookie detection. Useful for local testing of the mobile / desktop shells
    | from a desktop browser, and for app builds whose WebView does not send
    | a distinguishable signature.
    |
    | Accepted: android, ios, electron, windows, macos, web
    |
    | Leave null to auto-detect.
    |
    */

    'force_platform' => env('CAPACITOR_PLATFORM'),

    /*
    |--------------------------------------------------------------------------
    | Capacitor Shell URLs
    |--------------------------------------------------------------------------
    |
    | Base URL of the Laravel server each Capacitor shell loads, and the panel
    | path it lands on. The Android emulator reaches the host machine through
    | 10.0.2.2; a physical device needs your LAN IP; production needs the domain.
    |
    | Keep these in step with each shell's own `capacitor.config.json` and
    | `src/js/launcher.js`, which are what the app actually loads. They are set
    | with `npm run set:url -- <panel-url>` from inside the shell directory
    | (see app/Capacitor/{UserApp,AdminApp}/scripts/set-url.mjs), and are
    | mirrored here only so PHP-side helpers can build absolute URLs without
    | reading the shell's files.
    |
    */

    'shells' => [
        'admin' => [
            'url' => env('CAPACITOR_ADMIN_URL', env('APP_URL')),
            'path' => '/admin',
            'app_id' => env('CAPACITOR_ADMIN_APP_ID', 'id.dekorasi.pengantin.admin'),
        ],

        'user' => [
            'url' => env('CAPACITOR_USER_URL', env('APP_URL')),
            'path' => '/user',
            'app_id' => env('CAPACITOR_USER_APP_ID', 'id.dekorasi.pengantin.user'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Mobile Navigation
    |--------------------------------------------------------------------------
    |
    | The bottom navigation bar is rendered only when the request comes from a
    | phone-sized surface, and is suppressed on every auth page (login,
    | register, OTP, password reset). Set `enabled` to false to keep the
    | full-width sidebar drawer as the only mobile navigation.
    |
    | Each item accepts:
    |   label  -> string shown under the icon
    |   icon   -> Blade icon name (heroicon-*, eos-*, etc.)
    |   url    -> FQCN of a Filament Page/Resource (its getUrl() is used),
    |             or an array [ 'page' => FQCN, 'params' => [...] ],
    |             or a literal URL string
    |   match  -> 'exact' (default) or 'starts' for a prefix match when active
    |   badge  -> optional int|string|Closure rendered as a counter
    |
    */

    'mobile_nav' => [
        'enabled' => env('MOBILE_BOTTOM_NAV', true),

        // Viewport width (px) below which the mobile layout is used. Kept in
        // sync with the @media rules in resources/css/Shared/Shared.css.
        'breakpoint' => env('MOBILE_BREAKPOINT', 1023),

        'items' => [

            'admin' => [
                [
                    'label' => 'Beranda',
                    'icon' => '/images/icons/home.svg',
                    'url' => \App\Filament\Admin\Pages\Dashboard\Dashboard::class,
                ],
                [
                    'label' => 'Pesanan',
                    'icon' => '/images/icons/bag.svg',
                    'url' => \App\Filament\Admin\Resources\OrderResource\OrderResource::class,
                    'match' => 'starts',
                ],
                [
                    'label' => 'Produk',
                    'icon' => '/images/icons/swatch.svg',
                    'url' => \App\Filament\Admin\Resources\CatalogResource\CatalogResource::class,
                    'match' => 'starts',
                ],
                [
                    'label' => 'Pelanggan',
                    'icon' => '/images/icons/users.svg',
                    'url' => \App\Filament\Admin\Resources\UserResource\UserResource::class,
                    'match' => 'starts',
                ],
                [
                    'label' => 'Profil',
                    'icon' => '/images/icons/user_circle.svg',
                    'url' => \App\Filament\Admin\Pages\EditProfilePage\EditProfilePage::class,
                ],
            ],

            // Storefront: panel 'welcome' (nama yang dipakai panel yang
            // benar-benar merender) dan 'user' (nama lama) memakai set yang
            // sama persis. Lihat $storefrontNav di atas file ini.
            'welcome' => $storefrontNav,

            'user' => $storefrontNav,

        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Responsive Tables
    |--------------------------------------------------------------------------
    |
    | Filament v3 tables are desktop grids. Below the breakpoint each row is
    | re-rendered as a stacked card with label/value pairs, so wide tables
    | (Admin UserResource alone has 38 columns) stay readable on a phone.
    |
    */

    'responsive_tables' => [
        'enabled' => env('RESPONSIVE_TABLES', true),
        'breakpoint' => env('MOBILE_BREAKPOINT', 1023),

        // Tables already built from Layout\Stack / Layout\Split render their own
        // responsive markup, so card mode is skipped for them.
        'skip_when' => ['.fi-ta-grid', '.fi-ta-split'],
    ],

];
