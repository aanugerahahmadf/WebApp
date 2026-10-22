<?php

namespace App\Filament\Admin\Pages\EditProfilePage;

use App\Filament\Concerns\HasDynamicBreadcrumbs;
use App\Livewire\Admin\PersonalInfoComponentSuperAdmin\PersonalInfoComponentSuperAdmin;
use App\Livewire\Admin\UsernameComponent\UsernameComponent;
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
    use HasDynamicBreadcrumbs;

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
        $components = [
            PersonalInfoComponentSuperAdmin::class,
            UsernameComponent::class,
        ];

        return collect($components)
            ->sortBy(fn (string $component): int => (int) ($component::getSort() ?? 0))
            ->values()
            ->all();
    }

    public function getBreadcrumbs(): array
    {
        return [
            ...$this->breadcrumbParentCrumb(),
            $this->getTitle(),
        ];
    }
}
