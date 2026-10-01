<?php

namespace App\Filament\Welcome\Pages\CompleteProfilePage;

use App\Filament\Welcome\Pages\Home\Home;
use Filament\Pages\Page;

class CompleteProfilePage extends Page
{
    protected static string $view = 'Welcome.pages.complete-profile-page.complete-profile-page';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $maxWidth = 'md';

    public static function getSlug(): string
    {
        return 'complete-profile';
    }

    public function getTitle(): string
    {
        return __('Lengkapi Profil Anda');
    }

    public static function getNavigationLabel(): string
    {
        return __('Lengkapi Profil');
    }

    public function getBreadcrumbs(): array
    {
        return [
            Home::getUrl() => __('Beranda'),
            $this->getTitle(),
        ];
    }
}
