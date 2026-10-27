<?php

namespace App\Providers\PlatformSupportServiceProvider;

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

        // Platform marker (window.AppPlatform + <html data-platform> + kelas
        // .native-mobile / .native-desktop).
        //
        // Didaftarkan di HEAD_START, BUKAN SCRIPTS_AFTER seperti semula, dan
        // itu penting: penanda `.native-desktop` dibaca CSS untuk membedakan
        // "jendela sempit karena desktop app" dari "HP asli". Kalau skrip baru
        // jalan di akhir <body>, topbar sudah ter-render lebih dulu -- di
        // jendela desktop app yang dikecilkan, kotak Global Search sempat
        // melebar versi mobile lalu menyusut sendiri. HEAD_START ada di dalam
        // <head>, jadi documentElement sudah ada dan kelas terpasang sebelum
        // <body> di-parse.
        //
        // Melewatkan script ke depan tidak merusak apa pun: isinya hanya
        // menulis window.AppPlatform, atribut <html>, dan cookie (semuanya
        // dijaga `indexOf` supaya tidak menimpa nilai yang sudah ada).
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_START,
            fn (): View => view('User.components.platform-runtime-script.platform-runtime-script'),
        );

        // Pemulihan 419 untuk Livewire: dialog "This page has expired"
        // bawaan livewire.js diganti diam-diam ambil token baru + ulangi
        // request. Satu pendaftaran ini berlaku untuk ketiga panel, dan
        // dipasang di SCRIPTS_AFTER (setelah @filamentScripts) supaya
        // window.Livewire sudah ada ketika skrip dijalankan.
        //
        // Lapisan pertama tetap di server: `livewire/*` dikecualikan dari
        // verifikasi CSRF di bootstrap/app.php. Skrip ini jaring pengaman
        // untuk 419 yang penyebabnya bukan token (sesi benar-benar habis).
        FilamentView::registerRenderHook(
            PanelsRenderHook::SCRIPTS_AFTER,
            fn (): View => view('Shared.components.livewire-page-expired.livewire-page-expired'),
        );

        // Phone-only table transformation (resources/css/Shared/Mobile.css).
        // Panel-agnostic: it rewrites whatever <table> the current page rendered,
        // so one registration covers every panel and emits nothing on desktop —
        // the partial itself gates on AppPlatform::isMobile().
        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            fn (): View => view('Shared.components.responsive-tables.responsive-tables'),
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
