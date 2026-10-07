<?php

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
                    'icon' => 'heroicon-s-home',
                    'url' => \App\Filament\Admin\Pages\Dashboard\Dashboard::class,
                ],
                [
                    'label' => 'Pesanan',
                    'icon' => 'heroicon-o-shopping-bag',
                    'url' => \App\Filament\Admin\Resources\OrderResource\OrderResource::class,
                    'match' => 'starts',
                ],
                [
                    'label' => 'Produk',
                    'icon' => 'heroicon-o-swatch',
                    'url' => \App\Filament\Admin\Resources\ProductResource\ProductResource::class,
                    'match' => 'starts',
                ],
                [
                    'label' => 'Pelanggan',
                    'icon' => 'heroicon-o-users',
                    'url' => \App\Filament\Admin\Resources\UserResource\UserResource::class,
                    'match' => 'starts',
                ],
                [
                    'label' => 'Profil',
                    'icon' => 'heroicon-o-user-circle',
                    'url' => \App\Filament\Admin\Pages\EditProfilePage\EditProfilePage::class,
                ],
            ],

            'user' => [
                [
                    'label' => 'Beranda',
                    'icon' => 'heroicon-s-home',
                    'url' => \App\Filament\User\Pages\Dashboard\Dashboard::class,
                ],
                [
                    'label' => 'Cari',
                    'icon' => 'heroicon-o-magnifying-glass',
                    'url' => \App\Filament\User\Pages\CbirSearchPage\CbirSearchPage::class,
                ],
                [
                    'label' => 'Pesanan',
                    'icon' => 'heroicon-o-clipboard-document-list',
                    'url' => \App\Filament\User\Resources\HistoryResource\HistoryResource::class,
                    'match' => 'starts',
                ],
                [
                    'label' => 'Pesan',
                    'icon' => 'heroicon-s-chat-bubble-left-right',
                    'url' => \App\Filament\User\Pages\MessagesPage\MessagesPage::class,
                    'match' => 'starts',
                ],
                [
                    'label' => 'Profil',
                    'icon' => 'heroicon-o-user-circle',
                    'url' => \App\Filament\User\Pages\EditProfilePage\EditProfilePage::class,
                ],
            ],

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
