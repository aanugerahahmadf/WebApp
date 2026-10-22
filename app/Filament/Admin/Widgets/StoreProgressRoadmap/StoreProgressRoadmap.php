<?php

namespace App\Filament\Admin\Widgets\StoreProgressRoadmap;

use App\Models\Catalog\Catalog;
use App\Models\Category\Category;
use App\Models\Order\Order;
use App\Models\Review\Review;
use App\Models\Transaction\Transaction;
use App\Models\Voucher\Voucher;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;

/**
 * Roadmap Pengelolaan Toko -- peta jalan setup toko untuk admin.
 *
 * Tiap langkah nyambung ke fitur manajemen yang terkait, jadi admin bisa
 * langsung membuka halaman yang belum rapi dari widget ini.
 *
 * HASIL DI-CACHE dengan kunci `store_progress_roadmap_v2` supaya dashboard
 * tidak menghitung ulang 7 query setiap kali dibuka. Test mel Forget()
 * kuncinya dulu supaya angkanya segar.
 */
class StoreProgressRoadmap extends Widget
{
    protected static string $view = 'Admin.widgets.store-progress-roadmap';

    protected static ?int $sort = 0;

    /**
     * Widget ini LEBIH LEBAR dari satu kolom dashboard.
     *
     * Default `Widget::$columnSpan` = 1, jadi kalau tidak di-set, roadmap
     * cuma occupy separuh lebar halaman -- dan grid 4 kolom di dalamnya
     * jadi sempit sampai kartu-kartunya terpotong.
     */
    protected int|string|array $columnSpan = 'full';

    /*
     * TIDAK lazy.
     *
     * Semua widget Filament lazy secara bawaan (`CanBeLazy::$isLazy = true`),
     * jadi isinya baru diambil lewat request Livewire terpisah saat widget
     * masuk viewport. Untuk roadmap setup toko ini：admin BUTUH daftar
     * langkahnya langsung terlihat, bukan skeleton kosong yang menunggu
     * scroll. Widget lain di dashboard tetap lazy.
     */
    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return auth()->guard('admin')->check();
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        return self::payload();
    }

    /**
     * @return array<string, mixed>
     */
    protected static function payload(): array
    {
        /*
         * Cache-nya HANYA lewat `Cache::remember`.
         *
         * Dulu ada juga properti static `$data` sebagai cache kedua. Itu
         * salah: static hidup selama satu proses (Octane/queue), jadi data
         * bisa basi jauh melewati TTL 60 detik, dan `Cache::forget()` tidak
         * pernah mengosongkannya. Cukup satu sumber cache.
         */
        return Cache::remember('store_progress_roadmap_v2', 60, function (): array {
            $catalogCount = Catalog::query()->count();
            $categoryCount = Category::query()->count();
            $voucherCount = Voucher::query()->where('is_active', true)->count();
            $orderCount = Order::query()->count();
            $paidCount = Order::query()->where('payment_status', 'paid')->count();
            $reviewCount = Review::query()->count();
            $revenue = (float) Order::query()->where('payment_status', 'paid')->sum('total_price');

            $steps = [
                [
                    'step_num' => 1,
                    'title' => __('Produk & Katalog'),
                    'description' => __('Tambahkan produk dekorasi beserta harga dan stoknya.'),
                    'icon' => '/images/icons/flower.svg',
                    'url' => \App\Filament\Admin\Resources\CatalogResource\CatalogResource::getUrl('index'),
                    'count' => $catalogCount,
                    'count_label' => $catalogCount.' '.__('produk'),
                    'done' => $catalogCount > 0,
                ],
                [
                    'step_num' => 2,
                    'title' => __('Kategori Produk'),
                    'description' => __('Kelompokkan produk agar mudah dicari shopper.'),
                    'icon' => '/images/icons/tag.svg',
                    'url' => \App\Filament\Admin\Resources\CatalogResource\CatalogResource::getUrl('index'),
                    'count' => $categoryCount,
                    'count_label' => $categoryCount.' '.__('kategori'),
                    'done' => $categoryCount > 0,
                ],
                [
                    'step_num' => 3,
                    'title' => __('Voucher & Promo'),
                    'description' => __('Buat voucher untuk menarik pelanggan kembali.'),
                    'icon' => '/images/icons/ticket.svg',
                    'url' => \App\Filament\Admin\Resources\VoucherResource\VoucherResource::getUrl('index'),
                    'count' => $voucherCount,
                    'count_label' => $voucherCount.' '.__('voucher'),
                    'done' => $voucherCount > 0,
                ],
                [
                    'step_num' => 4,
                    'title' => __('Pesanan Masuk'),
                    'description' => __('Pantau pesanan yang masuk dan siap dikirim.'),
                    'icon' => '/images/icons/bag.svg',
                    'url' => \App\Filament\Admin\Resources\OrderResource\OrderResource::getUrl('index'),
                    'count' => $orderCount,
                    'count_label' => $orderCount.' '.__('pesanan'),
                    'done' => $orderCount > 0,
                ],
                [
                    'step_num' => 5,
                    'title' => __('Pembayaran Lunas'),
                    'description' => __('Pastikan pembayaran pelanggan tercatat dan lunas.'),
                    'icon' => '/images/icons/check_circle.svg',
                    'url' => \App\Filament\Admin\Resources\OrderResource\OrderResource::getUrl('index'),
                    'count' => $paidCount,
                    'count_label' => $paidCount.' '.__('dibayar'),
                    'done' => $paidCount > 0,
                ],
                [
                    'step_num' => 6,
                    'title' => __('Ulasan Pelanggan'),
                    'description' => __('Ulasan membantu produk dan layanan berikutnya.'),
                    'icon' => '/images/icons/star.svg',
                    'url' => \App\Filament\Admin\Resources\ReviewResource\ReviewResource::getUrl('index'),
                    'count' => $reviewCount,
                    'count_label' => $reviewCount.' '.__('ulasan'),
                    'done' => $reviewCount > 0,
                ],
                [
                    'step_num' => 7,
                    'title' => __('Pendapatan Tercatat'),
                    'description' => __('Pendapatan tercatat di mutasi transaksi.'),
                    'icon' => '/images/icons/chart.svg',
                    'url' => \App\Filament\Admin\Resources\TransactionResource\TransactionResource::getUrl('index'),
                    'count' => (int) $revenue,
                    'count_label' => 'Rp '.number_format($revenue, 0, ',', '.'),
                    'done' => $revenue > 0,
                ],
            ];

            $doneCount = count(array_filter($steps, fn (array $step): bool => $step['done']));
            $totalCount = count($steps);
            $progressPct = $totalCount > 0 ? (int) round($doneCount / $totalCount * 100) : 0;

            $statusLabel = match (true) {
                $progressPct >= 100 => __('Toko Anda sudah berjalan penuh!'),
                $progressPct >= 75 => __('Hampir sempurna, sedikit lagi!'),
                $progressPct >= 50 => __('Sudah halfway, terus semangat!'),
                $progressPct >= 25 => __('Sudah mulai jalan, lanjutkan!'),
                default => __('Mulai dari langkah pertama!'),
            };

            return [
                'steps' => $steps,
                'doneCount' => $doneCount,
                'totalCount' => $totalCount,
                'progressPct' => $progressPct,
                'statusLabel' => $statusLabel,
            ];
        });
    }
}
