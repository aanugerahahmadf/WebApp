<?php

namespace App\Filament\Welcome\Widgets\ShortcutStats;

use App\Models\Cart\Cart;
use App\Models\Order\Order;
use App\Models\Wishlist\Wishlist;
use Filament\Support\Enums\IconPosition;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

/**
 * Empat kartu aksi beranda: | Pesanan Saya | Favorit | Voucher Aktif | Keranjang |.
 * Aman untuk guest (semua angka 0).
 *
 * Tata letaknya per platform, dikerjakan di view
 * resources/views/Shared/widgets/shortcut-stats.blade.php — kelas PHP ini
 * sengaja tidak branching per platform:
 *
 *   - mobile (aplikasi shell Android/iOS + browser mobile): satu kartu per
 *     halaman, digeser dengan swipe, lengkap dengan titik penanda dan
 *     animasi slide berurutan saat masuk. Empat kolom di lebar 400px cuma
 *     berdesakan dan teksnya terpotong.
 *   - tablet, macOS, desktop, desktop app: grid 4 kolom seperti biasa.
 *
 * Widget ini memakai view `Shared.*` yang sama dengan panel User, jadi
 * perubahan view-nya berlaku di kedua panel sekaligus.
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
        // Panel welcome bisa dijelajahi guest: semua lookup user di-null-kan
        // dengan aman sehingga widget tetap tampil (angka 0) tanpa fatal.
        $user = Auth::user();
        $userId = $user?->id;

        return [
            Stat::make(__('Pesanan Saya'), $userId ? Order::query()->where('user_id', $userId)->count('id') : 0)
                ->description(__('Transaksi'))
                ->descriptionIcon('heroicon-m-shopping-bag', IconPosition::Before)
                ->color('info')
                ->extraAttributes([
                    'class' => 'home-stat-card home-stat-action cursor-pointer hover:scale-105 transition-transform h-full',
                    'onclick' => "window.location.href='".route('filament.welcome.resources.orders.index')."'",
                ]),
            Stat::make(__('Favorit'), $userId ? Wishlist::query()->where('user_id', $userId)->count('id') : 0)
                ->description(__('Tersimpan'))
                ->descriptionIcon('heroicon-m-heart', IconPosition::Before)
                ->color('danger')
                ->extraAttributes([
                    'class' => 'home-stat-card home-stat-action cursor-pointer hover:scale-105 transition-transform h-full',
                    'onclick' => "window.location.href='".route('filament.welcome.resources.wishlists.index')."'",
                ]),
            Stat::make(__('Voucher Aktif'), $user ? $user->vouchers()->whereNull('user_vouchers.used_at')->count() : 0)
                ->description(__('Diskon'))
                ->descriptionIcon('heroicon-m-ticket', IconPosition::Before)
                ->color('warning')
                ->extraAttributes([
                    'class' => 'home-stat-card home-stat-action cursor-pointer hover:scale-105 transition-transform h-full',
                    'onclick' => "window.location.href='".route('filament.welcome.resources.vouchers.index')."'",
                ]),
            Stat::make(__('Keranjang'), $userId ? Cart::query()->where('user_id', $userId)->count() : 0)
                ->description(__('Checkout'))
                ->descriptionIcon('heroicon-m-shopping-cart', IconPosition::Before)
                ->color('success')
                ->extraAttributes([
                    'class' => 'home-stat-card home-stat-action cursor-pointer hover:scale-105 transition-transform h-full',
                    'onclick' => "window.location.href='".route('filament.welcome.resources.carts.index')."'",
                ]),
        ];
    }
}
