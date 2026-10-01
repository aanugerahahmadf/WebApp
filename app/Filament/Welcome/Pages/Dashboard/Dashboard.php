<?php

namespace App\Filament\Welcome\Pages\Dashboard;

use App\Filament\Welcome\Widgets\CombinedCatalogWidget\CombinedCatalogWidget;
use App\Filament\Welcome\Widgets\StatsOverview\StatsOverview;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\Auth;

class Dashboard extends BaseDashboard
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
        // StatsOverview reads the signed-in user (orders, wishlist, cart,
        // vouchers) and would fatal on null. This panel is browsable by guests,
        // who get the catalog only -- which is also all the storefront has to
        // show them.
        if (! Auth::check()) {
            return [
                CombinedCatalogWidget::class,
            ];
        }

        return [
            StatsOverview::class,
            CombinedCatalogWidget::class,
        ];
    }

    public function getTitle(): string
    {
        return __('Beranda');
    }
}
