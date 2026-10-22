<?php

namespace App\Filament\Admin\Pages\WebsiteMobileCustomizerPage;

use App\Filament\Concerns\HasDynamicBreadcrumbs;
use App\Support\AppIcon\AppIcon;
use Filament\Pages\Page;

class WebsiteMobileCustomizerPage extends Page
{
    use HasDynamicBreadcrumbs;

    protected static string $view = 'Admin.pages.website-mobile-customizer.website-mobile-customizer-page';

    protected static ?string $navigationIcon = AppIcon::CUSTOMIZER;

    protected static ?int $navigationSort = 4;

    public static function getNavigationGroup(): ?string
    {
        return __('Pengaturan Sistem');
    }

    public function getTitle(): string
    {
        return __('Kustomisasi Website, Mobile Android & iOS');
    }

    public static function getNavigationLabel(): string
    {
        return __('Kustomisasi Website, Mobile Android & iOS');
    }

    public static function getSlug(): string
    {
        return 'website-mobile-customizer';
    }

    public function getBreadcrumbs(): array
    {
        return [
            ...$this->breadcrumbParentCrumb(),
            $this->getTitle(),
        ];
    }
}
