<?php

namespace App\Filament\User\Widgets\QuickAccessWidget;

use App\Filament\User\Resources\PackageResource\PackageResource;
use App\Filament\User\Resources\ProductResource\ProductResource;
use App\Filament\User\Resources\ReviewResource\ReviewResource;
use App\Filament\User\Resources\VoucherResource\VoucherResource;
use App\Filament\User\Resources\WishlistResource\WishlistResource;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

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
                ->icon('heroicon-m-cube')
                ->color('primary')
                ->extraAttributes([
                    'class' => 'home-stat-card home-stat-action cursor-pointer hover:scale-105 transition-transform h-full',
                    'onclick' => "window.location.href='" . route('filament.user.resources.packages.index') . "'",
                ]),

            Stat::make(__('Produk'), null)
                ->icon('heroicon-m-shopping-bag')
                ->color('info')
                ->extraAttributes([
                    'class' => 'home-stat-card home-stat-action cursor-pointer hover:scale-105 transition-transform h-full',
                    'onclick' => "window.location.href='" . route('filament.user.resources.products.index') . "'",
                ]),

            Stat::make(__('Ulasan'), null)
                ->icon('heroicon-m-star')
                ->color('warning')
                ->extraAttributes([
                    'class' => 'home-stat-card home-stat-action cursor-pointer hover:scale-105 transition-transform h-full',
                    'onclick' => "window.location.href='" . route('filament.user.resources.reviews.index') . "'",
                ]),

            Stat::make(__('Favorit'), null)
                ->icon('heroicon-m-heart')
                ->color('danger')
                ->extraAttributes([
                    'class' => 'home-stat-card home-stat-action cursor-pointer hover:scale-105 transition-transform h-full',
                    'onclick' => "window.location.href='" . route('filament.user.resources.wishlists.index') . "'",
                ]),

            Stat::make(__('Voucher'), null)
                ->icon('heroicon-m-ticket')
                ->color('success')
                ->extraAttributes([
                    'class' => 'home-stat-card home-stat-action cursor-pointer hover:scale-105 transition-transform h-full',
                    'onclick' => "window.location.href='" . route('filament.user.resources.vouchers.index') . "'",
                ]),
        ];
    }
}
