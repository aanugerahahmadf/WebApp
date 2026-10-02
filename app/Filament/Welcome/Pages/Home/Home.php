<?php

namespace App\Filament\Welcome\Pages\Home;

use App\Filament\Welcome\Widgets\CombinedCatalogWidget\CombinedCatalogWidget;
use App\Filament\Welcome\Widgets\ShortcutStats\ShortcutStats;
use App\Filament\Welcome\Widgets\StatsOverview\StatsOverview;
use Filament\Pages\Dashboard as BaseDashboard;

class Home extends BaseDashboard
{
    protected static string $routePath = 'home';

    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public static function getRouteBaseName(): string
    {
        return 'filament.welcome.pages.home';
    }

    public static function getSlug(): string
    {
        return 'home';
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Beranda');
    }

    public static function getNavigationLabel(): string
    {
        return __('Beranda');
    }

    public static function getNavigationIcon(): ?string
    {
        return static::$navigationIcon;
    }

    public function getWidgets(): array
    {
        // StatsOverview aman untuk guest (angka 0 + sapaan "Tamu"),
        // jadi selalu ditampilkan di storefront.
        return [
            StatsOverview::class,
            ShortcutStats::class,
            CombinedCatalogWidget::class,
        ];
    }

    public function getTitle(): string
    {
        return __('Beranda');
    }
}
