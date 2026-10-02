<?php

namespace App\Filament\User\Widgets\ShortcutStats;

use App\Models\Cart\Cart;
use App\Models\Order\Order;
use App\Models\Wishlist\Wishlist;
use Filament\Support\Enums\IconPosition;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

/**
 * Empat kartu aksi beranda, sebaris 4 kolom (Row 1) di SEMUA platform
 * (website laptop, browser Android/iOS, aplikasi mobile, aplikasi desktop):
 * | My Orders | Favorite | Active Voucher | Cart |
 */
class ShortcutStats extends BaseWidget
{
    protected static ?int $navigationSort = 2;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    /**
     * View sendiri (bukan bawaan Filament) agar grid 4 kolom ditulis inline
     * dan tidak bisa ditimpa stylesheet mana pun.
     *
     * @var view-string
     */
    protected static string $view = 'Shared.widgets.shortcut-stats';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $user = Auth::user();

        // Selalu tampilkan keempat kartu di semua platform
        // (website, browser mobile, aplikasi mobile, aplikasi desktop).
        return [
            Stat::make(__('Pesanan Saya'), Order::query()->where('user_id', $user->id)->count('id'))
                ->description(__('Transaksi'))
                ->descriptionIcon('heroicon-m-shopping-bag', IconPosition::Before)
                ->color('info')
                ->extraAttributes([
                    'class' => 'home-stat-card home-stat-action cursor-pointer hover:scale-105 transition-transform h-full',
                    'onclick' => "window.location.href='".route('filament.user.resources.orders.index')."'",
                ]),

            Stat::make(__('Favorit'), Wishlist::query()->where('user_id', $user->id)->count('id'))
                ->description(__('Tersimpan'))
                ->descriptionIcon('heroicon-m-heart', IconPosition::Before)
                ->color('danger')
                ->extraAttributes([
                    'class' => 'home-stat-card home-stat-action cursor-pointer hover:scale-105 transition-transform h-full',
                    'onclick' => "window.location.href='".route('filament.user.resources.wishlists.index')."'",
                ]),

            Stat::make(__('Voucher Aktif'), $user->vouchers()->whereNull('user_vouchers.used_at')->count())
                ->description(__('Diskon'))
                ->descriptionIcon('heroicon-m-ticket', IconPosition::Before)
                ->color('warning')
                ->extraAttributes([
                    'class' => 'home-stat-card home-stat-action cursor-pointer hover:scale-105 transition-transform h-full',
                    'onclick' => "window.location.href='".route('filament.user.resources.vouchers.index')."'",
                ]),

            Stat::make(__('Keranjang'), Cart::query()->where('user_id', $user->id)->count())
                ->description(__('Checkout'))
                ->descriptionIcon('heroicon-m-shopping-cart', IconPosition::Before)
                ->color('success')
                ->extraAttributes([
                    'class' => 'home-stat-card home-stat-action cursor-pointer hover:scale-105 transition-transform h-full',
                    'onclick' => "window.location.href='".route('filament.user.resources.carts.index')."'",
                ]),
        ];
    }
}
