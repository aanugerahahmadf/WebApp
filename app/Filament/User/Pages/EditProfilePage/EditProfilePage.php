<?php

namespace App\Filament\User\Pages\EditProfilePage;

use App\Livewire\User\PersonalInfoComponent\PersonalInfoComponent;
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
            PersonalInfoComponent::class,
        ];

        return collect($components)
            ->sortBy(fn (string $component) => $component::getSort())
            ->all();
    }
}
