<?php

namespace App\Filament\Admin\Pages\Home;

use App\Filament\Admin\Widgets\OrdersChart\OrdersChart;
use App\Filament\Admin\Widgets\RecentOrders\RecentOrders;
use App\Filament\Admin\Widgets\RevenueChart\RevenueChart;
use App\Filament\Admin\Widgets\StatsOverview\StatsOverview;
use Filament\Pages\Dashboard as BaseDashboard;

class Home extends BaseDashboard
{
    protected static string $routePath = 'home';

    protected static ?string $navigationIcon = 'heroicon-s-home';

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Beranda');
    }

    protected static ?int $navigationSort = 1;

    public static function getNavigationIcon(): ?string
    {
        return static::$navigationIcon;
    }

    public static function getNavigationLabel(): string
    {
        return __('Beranda');
    }

    public function getColumns(): int|array
    {
        return 2;
    }

    public function getWidgets(): array
    {
        return [
            StatsOverview::class,
            RevenueChart::class,
            OrdersChart::class,
            RecentOrders::class,
        ];
    }

    public function getTitle(): string
    {
        return __('Beranda');
    }
}
