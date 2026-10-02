<?php

namespace App\Filament\Welcome\Pages\SettingsPage;

use App\Filament\Concerns\HasDynamicBreadcrumbs;
use App\Filament\Welcome\Pages\Home\Home;
use App\Livewire\Welcome\DeleteAccountComponent\DeleteAccountComponent;
use Filament\Pages\Page;

class SettingsPage extends Page
{
    use HasDynamicBreadcrumbs;

    protected static string $view = 'Welcome.pages.settings-page.settings-page';

    protected static bool $shouldRegisterNavigation = false;

    public function getTitle(): string
    {
        return __('Pengaturan');
    }

    public static function getNavigationLabel(): string
    {
        return __('Pengaturan');
    }

    public static function getSlug(): string
    {
        return 'settings';
    }

    public function getRegisteredCustomProfileComponents(): array
    {
        return [
            DeleteAccountComponent::class,
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            ...$this->breadcrumbParentCrumb(),
            $this->getTitle(),
        ];
    }
}
