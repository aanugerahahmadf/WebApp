<?php

namespace App\Filament\User\Pages\EditProfilePage;

use App\Filament\Concerns\HasDynamicBreadcrumbs;
use App\Filament\User\Pages\Home\Home;
use App\Livewire\User\PersonalInfoComponent\PersonalInfoComponent;
use Filament\Pages\Page;

class EditProfilePage extends Page
{
    use HasDynamicBreadcrumbs;

    protected static string $view = 'User.pages.edit-profile.edit-profile';

    protected static ?string $navigationIcon = 'heroicon-s-user-circle';

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
            ...$this->breadcrumbParentCrumb(),
            $this->getTitle(),
        ];
    }
}
