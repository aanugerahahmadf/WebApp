<?php

namespace App\Providers\NativeAppServiceProvider;

use Native\Laravel\Contracts\ProvidesPhpIni;
use Native\Laravel\Facades\Menu;
use Native\Laravel\Facades\Window;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    /**
     * Executed once the NativePHP Desktop (Electron) application has booted.
     * Opens the main window and configures the native menu bar.
     */
    public function boot(): void
    {
        // ── Main Window ───────────────────────────────────────────────────
        Window::open()
            ->title('Dekorasi Bunga Pernikahan')
            ->width(1280)
            ->height(800)
            ->minWidth(800)
            ->minHeight(600)
            ->url('/user')
            ->focusable(true)
            ->resizable(true)
            ->showDevTools(env('APP_DEBUG', false));

        // ── Native Menu Bar ───────────────────────────────────────────────
        // Build a minimal app menu with navigation links and standard roles.
        Menu::create(
            Menu::app(),
            Menu::make(
                Menu::link('/user', 'Beranda'),
                Menu::link('/user/orders', 'Pesanan'),
                Menu::link('/user/wishlists', 'Wishlist'),
                Menu::separator(),
                Menu::link('/user/edit-profile-page', 'Profil'),
                Menu::separator(),
                Menu::quit(),
            )->label('Aplikasi'),
            Menu::edit(),
            Menu::view(),
            Menu::window(),
        );
    }

    /**
     * Return an array of php.ini directives to be set for the Electron PHP process.
     */
    public function phpIni(): array
    {
        return [
            'memory_limit' => '512M',
            'opcache.enable' => '1',
            'opcache.memory_consumption' => '128',
            'opcache.interned_strings_buffer' => '8',
            'opcache.max_accelerated_files' => '4000',
            'opcache.revalidate_freq' => '0',
            'opcache.validate_timestamps' => '0',
        ];
    }
}
