<?php

use App\Providers\AppServiceProvider\AppServiceProvider;
use App\Providers\AutoTranslationServiceProvider\AutoTranslationServiceProvider;
use App\Providers\Filament\AdminPanelProvider\AdminPanelProvider;
use App\Providers\Filament\WelcomePanelProvider\WelcomePanelProvider;
use App\Providers\GraphQLServiceProvider;
use App\Providers\GrpcServiceProvider\GrpcServiceProvider;
use App\Providers\PlatformModeServiceProvider\PlatformModeServiceProvider;
use App\Providers\PlatformSupportServiceProvider\PlatformSupportServiceProvider;
use App\Providers\VoltServiceProvider\VoltServiceProvider;
use Laravel\Boost\BoostServiceProvider;

// SHOPEE-STYLE SATU PANEL: UserPanelProvider sudah dilebur ke WelcomePanelProvider.
// Panel 'welcome' (path '/') sekarang memuat storefront tamu + halaman auth
// (/signin, /signup, /auth, OTP) + seluruh halaman akun (pengaturan, riwayat,
// keranjang, pesan...). Bookmark /user/* dilayani redirect legacy di
// routes/web/web.php.
return [
    PlatformModeServiceProvider::class,
    AppServiceProvider::class,
    AutoTranslationServiceProvider::class,
    AdminPanelProvider::class,
    WelcomePanelProvider::class,
    PlatformSupportServiceProvider::class,
    VoltServiceProvider::class,
    BoostServiceProvider::class,
    GrpcServiceProvider::class,
    GraphQLServiceProvider::class,
];
