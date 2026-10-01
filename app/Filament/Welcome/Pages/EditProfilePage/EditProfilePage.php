<?php

namespace App\Filament\Welcome\Pages\EditProfilePage;

use App\Filament\Welcome\Pages\Home\Home;
use App\Livewire\Welcome\PersonalInfoComponent\PersonalInfoComponent;
use Filament\Pages\Page;

class EditProfilePage extends Page
{
    protected static string $view = 'Welcome.pages.edit-profile.edit-profile';

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

    public function getBreadcrumbs(): array
    {
        return [
            Home::getUrl() => __('Beranda'),
            $this->getTitle(),
        ];
    }
}
