<?php

namespace App\Filament\Admin\Pages\EditProfilePage;

use App\Livewire\Admin\PersonalInfoComponentSuperAdmin\PersonalInfoComponentSuperAdmin;
use App\Support\AppIcon\AppIcon;
use Filament\Pages\Page;

/**
 * Halaman profil admin.
 *
 * Komponen profil (nama, username) dirangkai di sini lewat
 * `getRegisteredCustomProfileComponents()`, lalu dirender oleh view
 * `Admin.pages.edit-profile.edit-profile`.
 */
class EditProfilePage extends Page
{

    protected static string $view = 'Admin.pages.edit-profile.edit-profile';

    protected static ?string $navigationIcon = AppIcon::PROFILE;

    protected static bool $shouldRegisterNavigation = false;

    public function getTitle(): string
    {
        return __('Profil');
    }

    public static function getNavigationLabel(): string
    {
        return __('Profil');
    }

    /**
     * @return array<int, class-string>
     */
    public function getRegisteredCustomProfileComponents(): array
    {
        // UsernameComponent TIDAK lagi didaftarkan (2026-10-26): field
        // username sudah pindah ke dalam section "Informasi Profil" di
        // PersonalInfoComponentSuperAdmin, sama seperti panel User.
        //
        // Class app/Livewire/Admin/UsernameComponent/UsernameComponent.php
        // dan view-nya SENGAJA tidak dihapus -- kalau section terpisah itu
        // dibutuhkan lagi, cukup daftarkan ulang di sini.
        $components = [
            PersonalInfoComponentSuperAdmin::class,
        ];

        return collect($components)
            ->sortBy(fn (string $component): int => (int) ($component::getSort() ?? 0))
            ->values()
            ->all();
    }

        public function getBreadcrumbs(): array
    {
        return [];
    }
}
