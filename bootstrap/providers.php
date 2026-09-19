<?php

use App\Providers\AppServiceProvider\AppServiceProvider;
use App\Providers\AutoTranslationServiceProvider\AutoTranslationServiceProvider;
use App\Providers\Filament\AdminPanelProvider\AdminPanelProvider;
use App\Providers\Filament\UserPanelProvider\UserPanelProvider;
use App\Providers\NativeServiceProvider\NativeServiceProvider;
use App\Providers\PlatformModeServiceProvider\PlatformModeServiceProvider;
use App\Providers\PlatformSupportServiceProvider\PlatformSupportServiceProvider;
use App\Providers\VoltServiceProvider\VoltServiceProvider;
use Laravel\Boost\BoostServiceProvider;

return [
    PlatformModeServiceProvider::class,
    AppServiceProvider::class,
    AutoTranslationServiceProvider::class,
    AdminPanelProvider::class,
    UserPanelProvider::class,
    NativeServiceProvider::class,
    PlatformSupportServiceProvider::class,
    VoltServiceProvider::class,
    BoostServiceProvider::class,
];
