<?php

namespace App\Filament\User\Widgets\QuickAccessWidget;

use App\Filament\User\Resources\CatalogResource\CatalogResource;
use App\Filament\User\Resources\ReviewResource\ReviewResource;
use App\Filament\User\Resources\VoucherResource\VoucherResource;
use App\Filament\User\Resources\WishlistResource\WishlistResource;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Support\AppIcon\AppIcon;

class QuickAccessWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function getExtraAttributes(): array
    {
        return [
            'class' => implode(' ', [
                'quick-access-widget',
                '[&_.fi-wi-stats-overview-stats-ctn]:!grid',
                '[&_.fi-wi-stats-overview-stats-ctn]:!grid-cols-5',
                'max-md:[&_.fi-wi-stats-overview-stats-ctn]:!grid-cols-5',
                '[&_.fi-wi-stats-overview-stats-ctn]:!gap-3',
                '[&_.fi-wi-stats-overview-stat]:!p-3',
                '[&_.fi-wi-stats-overview-stat-label]:!text-xs',
                '[&_.fi-wi-stats-overview-stat-value]:!text-lg',
                'md:[&_.fi-wi-stats-overview-stats-ctn]:!gap-4',
            ]),
        ];
    }

    protected function getColumns(): int
    {
        return 5;
    }

    protected function getStats(): array
    {
        return [
            Stat::make(__('Paket'), null)
                ->icon(AppIcon::GIFT)
                ->color('primary')
                ->extraAttributes([
                    'class' => 'home-stat-card home-stat-action cursor-pointer hover:scale-105 transition-transform h-full',
                    'onclick' => "window.location.href='" . route('filament.welcome.resources.flowerdecorationscatalog.index') . "'",
                ]),

            Stat::make(__('Katalog'), null)
                ->icon(AppIcon::ORDER)
                ->color('info')
                ->extraAttributes([
                    'class' => 'home-stat-card home-stat-action cursor-pointer hover:scale-105 transition-transform h-full',
                    'onclick' => "window.location.href='" . route('filament.welcome.resources.flowerdecorationscatalog.index') . "'",
                ]),

            Stat::make(__('Ulasan'), null)
                ->icon('/images/icons/star.svg')
                ->color('warning')
                ->extraAttributes([
                    'class' => 'home-stat-card home-stat-action cursor-pointer hover:scale-105 transition-transform h-full',
                    'onclick' => "window.location.href='" . route('filament.welcome.resources.reviews.index') . "'",
                ]),

            Stat::make(__('Favorit'), null)
                ->icon(AppIcon::WISHLIST)
                ->color('danger')
                ->extraAttributes([
                    'class' => 'home-stat-card home-stat-action cursor-pointer hover:scale-105 transition-transform h-full',
                    'onclick' => "window.location.href='" . route('filament.welcome.resources.wishlists.index') . "'",
                ]),

            Stat::make(__('Voucher'), null)
                ->icon(AppIcon::VOUCHER)
                ->color('success')
                ->extraAttributes([
                    'class' => 'home-stat-card home-stat-action cursor-pointer hover:scale-105 transition-transform h-full',
                    'onclick' => "window.location.href='" . route('filament.welcome.resources.vouchers.index') . "'",
                ]),
        ];
    }
}
