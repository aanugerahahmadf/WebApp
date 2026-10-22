<?php

namespace App\Filament\User\Widgets\ShortcutStats;

use App\Models\Cart\Cart;
use App\Models\Order\Order;
use App\Models\Catalog\Catalog;
use App\Models\Wishlist\Wishlist;
use Filament\Support\Enums\IconPosition;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

/**
 * Enam kartu aksi beranda:
 * | Catalog Bunga | Pesanan Saya | Favorit | Voucher Aktif | Keranjang |.
 *
 * Dua kartu katalog deliberate ditaruh paling depan: itu pintu masuk
 * belanja, jadi yang pertama dilihat. Empat kartu setelahnya milik user
 * yang sedang login.
 *
 * Kartu katalog tidak bergantung pada user -- keduanya menghitung isi
 * katalog yang aktif (is_active), jadi angkanya sama untuk semua orang
 * yang membuka beranda.
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
 * Widget ini memakai view `Shared.*` yang sama dengan panel Welcome, jadi
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
     * Layout mobile (1 kartu + swipe) juga dikerjakan di view yang sama --
     * lihat docblock kelas.
     *
     * @var view-string
     */
    protected static string $view = 'Shared.widgets.shortcut-stats';

    protected function getColumns(): int
    {
        return 6;
    }

    protected function getStats(): array
    {
        $user = Auth::user();

        // Selalu tampilkan keenam kartu di semua platform
        // (website, browser mobile, aplikasi mobile, aplikasi desktop).
        // Urutannya: dua kartu katalog dulu (pintu masuk belanja), lalu
        // empat kartu milik user.
        return [
            // Kartu katalog: jumlahnya bukan milik user, jadi tidak perlu
            // login -- hanya katalog yang aktif (is_active), sama seperti
            // CombinedCatalogWidget yang juga menyusun katalog beranda.
            Stat::make(__('Catalog Bunga'), Catalog::query()->where('is_active', true)->count())
                ->description(__('Katalog'))
                ->descriptionIcon('/images/icons/flower.svg', IconPosition::Before)
                ->color('primary')
                ->extraAttributes([
                    'class' => 'home-stat-card home-stat-action cursor-pointer hover:scale-105 transition-transform h-full',
                    'onclick' => "window.location.href='".route('filament.welcome.resources.flowerdecorationscatalog.index')."'",
                ]),


            Stat::make(__('Pesanan Saya'), Order::query()->where('user_id', $user->id)->count('id'))
                ->description(__('Transaksi'))
                ->descriptionIcon('/images/icons/bag.svg', IconPosition::Before)
                ->color('info')
                ->extraAttributes([
                    'class' => 'home-stat-card home-stat-action cursor-pointer hover:scale-105 transition-transform h-full',
                    'onclick' => "window.location.href='".route('filament.welcome.resources.orders.index')."'",
                ]),

            Stat::make(__('Favorit'), Wishlist::query()->where('user_id', $user->id)->count('id'))
                ->description(__('Tersimpan'))
                ->descriptionIcon('/images/icons/heart.svg', IconPosition::Before)
                ->color('danger')
                ->extraAttributes([
                    'class' => 'home-stat-card home-stat-action cursor-pointer hover:scale-105 transition-transform h-full',
                    'onclick' => "window.location.href='".route('filament.welcome.resources.wishlists.index')."'",
                ]),

            Stat::make(__('Voucher Aktif'), $user->vouchers()->whereNull('user_vouchers.used_at')->count())
                ->description(__('Diskon'))
                ->descriptionIcon('/images/icons/ticket.svg', IconPosition::Before)
                ->color('warning')
                ->extraAttributes([
                    'class' => 'home-stat-card home-stat-action cursor-pointer hover:scale-105 transition-transform h-full',
                    'onclick' => "window.location.href='".route('filament.welcome.resources.vouchers.index')."'",
                ]),

            Stat::make(__('Keranjang'), Cart::query()->where('user_id', $user->id)->count())
                ->description(__('Checkout'))
                ->descriptionIcon('/images/icons/cart.svg', IconPosition::Before)
                ->color('success')
                ->extraAttributes([
                    'class' => 'home-stat-card home-stat-action cursor-pointer hover:scale-105 transition-transform h-full',
                    'onclick' => "window.location.href='".route('filament.welcome.resources.carts.index')."'",
                ]),
        ];
    }
}
