<?php

namespace App\Filament\Admin\Pages\FontSettingsPage;

use App\Support\AppIcon\AppIcon;
use Filament\Pages\Page;

/**
 * Halaman Tipografi panel admin.
 *
 * Isinya `FontSettingsComponent` (Livewire): dua pemilih huruf (website &
 * Dekstop App, serta panel admin & Dekstop Admin App) + form ukuran, tebal,
 * warna, radius, brand, dan CSS tambahan.
 *
 * Kenapa huruf dipilih lewat TOMBOL (modal grid) dan bukan dropdown select:
 * daftarnya 246+ keluarga huruf hasil `scripts/generate-fonts.py`, jadi
 * isinya harus bisa dilihat BENTUKNYA -- bukan cuma dibaca.
 */
class FontSettingsPage extends Page
{

    protected static string $view = 'Admin.pages.font-settings.font-settings-page';

    protected static ?string $navigationIcon = AppIcon::TEXT;

    protected static ?int $navigationSort = 6;

    /**
     * Group navigasi: "System Settings" (id: "Pengaturan Sistem").
     *
     * WAJIB pakai `__()` -- provider mendaftarkan group-nya sebagai
     * `NavigationGroup::make()->label(fn () => __('Pengaturan Sistem'))`,
     * jadi yang dicocokkan Filament adalah label SETELAH terjemahan.
     * Kalau halaman ini mengembalikan string mentah ('Pengaturan Sistem'),
     * di locale Inggris label group-nya jadi "System Settings" dan halamannya
     * TIDAK ikut ke group itu -- Filament membuat group sendiri yang tidak
     * pernah dikontrol siapa pun (group liar).
     */
    public static function getNavigationGroup(): ?string
    {
        return __('Pengaturan Sistem');
    }

    public function getTitle(): string
    {
        return __('Tipografi');
    }

    public static function getNavigationLabel(): string
    {
        return __('Tipografi');
    }

    public static function getSlug(): string
    {
        return 'tipografi';
    }

        public function getBreadcrumbs(): array
    {
        return [];
    }
}
