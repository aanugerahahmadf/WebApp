<?php

namespace App\Filament\Admin\Pages\ApiKeyManagerPage;

use App\Support\AppIcon\AppIcon;
use App\Support\AdminAccess\AdminAccess;
use Filament\Pages\Page;

class ApiKeyManagerPage extends Page
{

    protected static string $view = 'Admin.pages.api-key-manager.api-key-manager-page';

    protected static ?string $navigationIcon = AppIcon::API_KEY;

    protected static ?int $navigationSort = 203;

    public static function canAccess(): bool
    {
        return AdminAccess::allows('manage_api_keys');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Pengaturan Sistem');
    }

    public function getTitle(): string
    {
        return __('Manajemen API Key');
    }

    public static function getNavigationLabel(): string
    {
        return __('Manajemen API Key');
    }

    public static function getSlug(): string
    {
        return 'api-keys';
    }

        public function getBreadcrumbs(): array
    {
        return [];
    }
}
