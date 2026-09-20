<?php

namespace App\Filament\User\Widgets\StatsOverview;

use App\Models\Cart\Cart;
use App\Models\Order\Order;
use App\Models\Wishlist\Wishlist;
use App\Support\PlatformContext\PlatformContext;
use Filament\Support\Enums\IconPosition;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class StatsOverview extends BaseWidget
{
    protected static ?int $navigationSort = 1;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public function getExtraAttributes(): array
    {
        return [
            'class' => implode(' ', [
                'user-home-stats',
                '[&_.fi-wi-stats-overview-stats-ctn]:!grid',
                '[&_.fi-wi-stats-overview-stats-ctn]:!grid-cols-2',
                '[&_.fi-wi-stats-overview-stats-ctn]:!gap-3',
                '[&_.fi-wi-stats-overview-stat]:!p-4',
                '[&_.fi-wi-stats-overview-stat-label]:!text-sm',
                '[&_.fi-wi-stats-overview-stat-value]:!text-2xl',
                '[&_.fi-wi-stats-overview-stat-description]:!text-xs',
                'md:[&_.fi-wi-stats-overview-stats-ctn]:!gap-4',
            ]),
        ];
    }

    protected function getColumns(): int
    {
        return 2;
    }

    protected function getStats(): array
    {
        $user = Auth::user();
        $name = $user->full_name ?? $user->username ?? __('User');
        $isMobile = PlatformContext::isAnyMobile(); // Android / iOS (native app & mobile browser)

        $stats = [
            Stat::make(__('filament-panels::widgets/account-widget.welcome'), $name)
                ->description(__('Make your special moment today'))
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('primary')
                ->extraAttributes([
                    'class' => 'home-stat-card home-stat-welcome h-full col-span-2',
                    'style' => 'grid-column: 1 / -1;',
                ]),
        ];

        // My Orders (Transactions) — disembunyikan di mobile Android/iOS
        if (! $isMobile) {
            $stats[] = Stat::make(__('Pesanan Saya'), Order::query()->where('user_id', $user->id)->count('id'))
                ->description(__('Transaksi'))
                ->descriptionIcon('heroicon-m-shopping-bag', IconPosition::Before)
                ->color('info')
                ->extraAttributes([
                    'class' => 'home-stat-card home-stat-action cursor-pointer hover:scale-105 transition-transform h-full',
                    'onclick' => "window.location.href='".route('filament.user.resources.orders.index')."'",
                ]);
        }

        $stats[] = Stat::make(__('Favorit'), Wishlist::query()->where('user_id', $user->id)->count('id'))
            ->description(__('Tersimpan'))
            ->descriptionIcon('heroicon-m-heart', IconPosition::Before)
            ->color('danger')
            ->extraAttributes([
                'class' => 'home-stat-card home-stat-action cursor-pointer hover:scale-105 transition-transform h-full',
                'onclick' => "window.location.href='".route('filament.user.resources.wishlists.index')."'",
            ]);

        $stats[] = Stat::make(__('Voucher Aktif'), $user->vouchers()->whereNull('user_vouchers.used_at')->count())
            ->description(__('Diskon'))
            ->descriptionIcon('heroicon-m-ticket', IconPosition::Before)
            ->color('warning')
            ->extraAttributes([
                'class' => 'home-stat-card home-stat-action cursor-pointer hover:scale-105 transition-transform h-full',
                'onclick' => "window.location.href='".route('filament.user.resources.vouchers.index')."'",
            ]);

        // Cart (Checkout) — disembunyikan di mobile Android/iOS
        if (! $isMobile) {
            $stats[] = Stat::make(__('Keranjang'), Cart::query()->where('user_id', $user->id)->count())
                ->description(__('Checkout'))
                ->descriptionIcon('heroicon-m-shopping-cart', IconPosition::Before)
                ->color('success')
                ->extraAttributes([
                    'class' => 'home-stat-card home-stat-action cursor-pointer hover:scale-105 transition-transform h-full',
                    'onclick' => "window.location.href='".route('filament.user.resources.carts.index')."'",
                ]);
        }

        return $stats;
    }
}
