<?php

namespace App\Providers\PlatformSupportServiceProvider;

use Filament\Notifications\Livewire\DatabaseNotifications;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Support\ServiceProvider;

/**
 * Cross-platform Filament hooks: PWA (desktop install), language switcher, runtime detection.
 * Covers website + desktop app + mobile app on Windows, macOS, Android, and iOS.
 */
class PlatformSupportServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Trigger tersembunyi — bell custom di topbar/index.blade.php.
        // Filament's ->databaseNotifications() di UserPanelProvider sudah menangani
        // rendering modal Livewire secara otomatis di semua halaman (termasuk Cart).
        DatabaseNotifications::trigger('filament-panels::topbar.database-notifications-trigger');

        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): View => view('User.components.pwa-head.pwa-head'),
        );

        FilamentView::registerRenderHook(
            PanelsRenderHook::SCRIPTS_AFTER,
            fn (): View => view('User.components.platform-runtime-script.platform-runtime-script'),
        );

        // FilamentView::registerRenderHook(
        //     PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE,
        //     fn (): View => view('User.filament-language-switcher.language-switcher.language-switcher'),
        // );

        // FilamentView::registerRenderHook(
        //     PanelsRenderHook::AUTH_REGISTER_FORM_BEFORE,
        //     fn (): View => view('User.filament-language-switcher.language-switcher.language-switcher'),
        // );

        // FilamentView::registerRenderHook(
        //     PanelsRenderHook::AUTH_PASSWORD_RESET_REQUEST_FORM_BEFORE,
        //     fn (): View => view('User.filament-language-switcher.language-switcher.language-switcher'),
        // );
    }
}
