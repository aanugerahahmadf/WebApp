<?php

namespace App\Providers\PlatformSupportServiceProvider;

use App\Support\MobileNav\MobileNav;
use Filament\Notifications\Livewire\DatabaseNotifications;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Support\ServiceProvider;

/**
 * Cross-platform Filament hooks: PWA (desktop install), runtime detection,
 * phone-only responsive tables, and the phone-only bottom navigation.
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

        // Phone-only table transformation (resources/css/Shared/Mobile.css).
        // Panel-agnostic: it rewrites whatever <table> the current page rendered,
        // so one registration covers every panel and emits nothing on desktop —
        // the partial itself gates on AppPlatform::isMobile().
        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            fn (): View => view('Shared.components.responsive-tables.responsive-tables'),
        );

        // Phone-only bottom navigation. The destinations are per panel, declared
        // under `app-platform.mobile_nav.items.{panel}`, so this hook stays generic
        // and lets MobileNav resolve the current panel; shouldRender() is what
        // suppresses it on auth pages, on desktop, and for any panel that declares
        // no destinations.
        //
        // WelcomePanelProvider registers a second, 'user'-keyed hook of its own:
        // the storefront is browsable by guests, and config declares no 'welcome'
        // destinations, so the bar it wants is the account one.
        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            fn (): View|string => MobileNav::shouldRender()
                ? view('Shared.components.mobile-bottom-nav.mobile-bottom-nav')
                : '',
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
