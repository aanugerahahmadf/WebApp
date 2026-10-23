<?php

namespace App\Filament\User\Pages\Home;

use App\Filament\User\Widgets\CombinedCatalogWidget\CombinedCatalogWidget;
use App\Filament\User\Widgets\ShortcutStats\ShortcutStats;
use App\Filament\User\Widgets\StatsOverview\StatsOverview;
use App\Support\AppIcon\AppIcon;
use Filament\Pages\Dashboard as BaseDashboard;

class Home extends BaseDashboard
{
    protected static string $routePath = 'home';

    protected static ?string $navigationIcon = AppIcon::HOME;

    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    /**
     * Panel 'user' sudah dilebur ke panel 'welcome', jadi route home-nya
     * sekarang bernama 'filament.welcome.pages.home'.
     *
     * Nama route lama ('filament.user.pages.home') tidak ada lagi. Kalau
     * dipanggil begitu saja, hasilnya RouteNotFoundException -- bukan 404
     * yang ramah, tapi exception yang menggagalkan halaman.
     */
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
