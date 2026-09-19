<?php

namespace App\Filament\Admin\Pages\EditProfilePage;

use App\Livewire\Admin\PersonalInfoComponentSuperAdmin\PersonalInfoComponentSuperAdmin;
use App\Livewire\Admin\UsernameComponent\UsernameComponent;
use App\Livewire\Shared\BrowserSessionsComponent\BrowserSessionsComponent;
use App\Livewire\Shared\DeleteAccountComponent\DeleteAccountComponent;
use App\Livewire\Shared\EditPasswordComponent\EditPasswordComponent;
use App\Livewire\Shared\MobileSettingsComponent\MobileSettingsComponent;
use Filament\Pages\Page;

class EditProfilePage extends Page
{
    protected static string $view = 'User.pages.edit-profile.edit-profile';

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static bool $shouldRegisterNavigation = false;

    public function getTitle(): string
    {
        return __('Profil');
    }

    public static function getNavigationLabel(): string
    {
        return __('Profil');
    }

    public function getRegisteredCustomProfileComponents(): array
    {
        $components = [
            PersonalInfoComponentSuperAdmin::class,
            UsernameComponent::class,
            MobileSettingsComponent::class,
            EditPasswordComponent::class,
            BrowserSessionsComponent::class,
            DeleteAccountComponent::class,
        ];

        return collect($components)
            ->sortBy(fn (string $component) => $component::getSort())
            ->all();
    }
}
