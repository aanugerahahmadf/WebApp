<?php

namespace App\Filament\User\Pages\SettingsPage;

use App\Livewire\Shared\BrowserSessionsComponent\BrowserSessionsComponent;
use App\Livewire\Shared\DeleteAccountComponent\DeleteAccountComponent;
use Filament\Pages\Page;

class SettingsPage extends Page
{
    protected static string $view = 'User.pages.settings-page.settings-page';

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
            BrowserSessionsComponent::class,
            DeleteAccountComponent::class,
        ];
    }
}
