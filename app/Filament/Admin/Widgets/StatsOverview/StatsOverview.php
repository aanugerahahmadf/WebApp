<?php

namespace App\Filament\Admin\Widgets\StatsOverview;

use App\Enums\OrderPaymentStatus\OrderPaymentStatus;
use App\Models\Admin\Admin;
use App\Models\Catalog\Catalog;
use App\Models\Category\Category;
use App\Models\Discount\Discount;
use App\Models\Help\Help;
use App\Models\Order\Order;
use App\Models\Package\Package;
use App\Models\PaymentGateway\PaymentGateway;
use App\Models\PaymentMethod\PaymentMethod;
use App\Models\PrivacyPolicy\PrivacyPolicy;
use App\Models\ReferenceOption\ReferenceOption;
use App\Models\Report\Report;
use App\Models\Review\Review;
use App\Models\TermsOfService\TermsOfService;
use App\Models\Transaction\Transaction;
use App\Models\User\User;
use App\Models\Voucher\Voucher;
use App\Models\WeddingDecorationPolicy\WeddingDecorationPolicy;
use App\Models\Wishlist\Wishlist;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

/**
 * Ringkasan angka untuk dashboard panel admin.
 *
 * URUTANNYA PENTING: **Data Master dulu**, baru Transaksi. Dashboard
 * adalah tempat admin pertama kali menoleh, dan Data Master (= "Master
 * Data" di navigasi) adalah isi toko yang harus siap sebelum ada
 * transaksinya -- jadi angkanya yang didahulukan.
 *
 * | # | Kartu | Sumber |
 * |---|---|---|
 * | 1-10 | Data Master | Produk, Paket, Kategori, Pengguna, Admin Tim, Ulasan, Laporan, Opsi Referensi, Pusat Bantuan, Kebijakan |
 * | 11-18 | Transaksi | Pesanan, Belum Dibayar, Transaksi, Voucher, Diskon, Metode Pembayaran, Payment Gateway, Wishlist |
 *
 * LABEL-NYA pakai kunci yang SUDAH ADA di `lang/id.json` DAN `lang/en.json`
 * (kuncinya baru juga ditambahkan ke kedua berkas itu). Kalau kuncinya tidak
 * ada di salah satu berkas, `__()` akan autoc-translate -- dan hasilnya
 * campur dua bahasa di layar yang sama (judul Bahasa Inggris, deskripsi
 * Bahasa Indonesia).
 *
 * SEMUA angka diambil dalam SATU query per tabel, lalu di-cache bersama
 * (satu kunci cache, bukan 18). Widget ini dirender di dashboard yang
 * dibuka sering, dan 18 `Cache::remember()` terpisah berarti 18 percakapan
 * ke cache store per render.
 */
class StatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    /**
     * Widget Filament LAZY secara bawaan: isinya diambil lewat request
     * Livewire terpisah saat widget masuk viewport, dan HTML awal cuma
     * berisi skeleton. Untuk dashboard admin, angkanya harus LANGSUNG
     * terlihat -- jadi lazy dimatikan.
     */
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $counts = $this->counts();

        return [
            // ── DATA MASTER ────────────────────────────────────────────
            Stat::make(__('Total Produk'), (string) $counts['catalogs'])
                ->description(__('Produk aktif'))
                ->descriptionIcon('/images/icons/flower.svg')
                ->color('primary'),

            Stat::make(__('Total Paket'), (string) $counts['packages'])
                ->description(__('Nama paket dekorasi'))
                ->descriptionIcon('/images/icons/gift.svg')
                ->color('primary'),

            Stat::make(__('Total Kategori'), (string) $counts['categories'])
                ->description(__('Kategori produk'))
                ->descriptionIcon('/images/icons/bookmark.svg')
                ->color('primary'),

            Stat::make(__('Total Pengguna'), (string) $counts['users'])
                ->description(__('Terdaftar di aplikasi'))
                ->descriptionIcon('/images/icons/users.svg')
                ->color('info'),

            Stat::make(__('Total Admin Tim'), (string) $counts['admins'])
                ->description(__('Akun tim internal'))
                ->descriptionIcon('/images/icons/admin-shield.svg')
                ->color('info'),

            Stat::make(__('Total Ulasan'), (string) $counts['reviews'])
                ->description(__('Ulasan dari pelanggan'))
                ->descriptionIcon('/images/icons/star.svg')
                ->color('info'),

            Stat::make(__('Total Laporan'), (string) $counts['reports'])
                ->description(__('Laporan dari pengguna'))
                ->descriptionIcon('/images/icons/report.svg')
                ->color('warning'),

            Stat::make(__('Opsi Referensi'), (string) $counts['reference_options'])
                ->description(__('Tipe dan nilai acuan'))
                ->descriptionIcon('/images/icons/reference.svg')
                ->color('gray'),

            Stat::make(__('Pusat Bantuan'), (string) $counts['helps'])
                ->description(__('Artikel pusat bantuan'))
                ->descriptionIcon('/images/icons/help.svg')
                ->color('gray'),

            Stat::make(__('Total Kebijakan'), (string) $counts['policies'])
                ->description(__('Dokumen legal aplikasi'))
                ->descriptionIcon('/images/icons/wedding-policy.svg')
                ->color('gray'),

            // ── TRANSAKSI ──────────────────────────────────────────────
            Stat::make(__('Total Pesanan'), (string) $counts['orders'])
                ->description(__('Pesanan pelanggan'))
                ->descriptionIcon('/images/icons/bag.svg')
                ->color('success'),

            Stat::make(__('Belum dibayar'), (string) $counts['unpaid_orders'])
                ->description(__('Total Pesanan'))
                ->descriptionIcon('/images/icons/clock.svg')
                ->color('danger'),

            Stat::make(__('Total Transaksi'), (string) $counts['transactions'])
                ->description(__('Riwayat pembayaran'))
                ->descriptionIcon('/images/icons/transaction.svg')
                ->color('success'),

            Stat::make(__('Total Voucher'), (string) $counts['vouchers'])
                ->description(__('Kode promo tersedia'))
                ->descriptionIcon('/images/icons/voucher.svg')
                ->color('success'),

            Stat::make(__('Total Diskon'), (string) $counts['discounts'])
                ->description(__('Penawaran harga'))
                ->descriptionIcon('/images/icons/discount.svg')
                ->color('success'),

            Stat::make(__('Metode Pembayaran'), (string) $counts['payment_methods'])
                ->description(__('Metode pembayaran tersedia'))
                ->descriptionIcon('/images/icons/payment-method.svg')
                ->color('warning'),

            Stat::make(__('Payment Gateway'), (string) $counts['payment_gateways'])
                ->description(__('Kanal pembayaran daring'))
                ->descriptionIcon('/images/icons/payment-gateway.svg')
                ->color('warning'),

            Stat::make(__('Total Wishlist'), (string) $counts['wishlists'])
                ->description(__('Produk tersimpan'))
                ->descriptionIcon('/images/icons/wishlist.svg')
                ->color('gray'),
        ];
    }

    /**
     * Semua angka dalam SATU cache.
     *
     * "Paket" dihitung dari NAMA NYA yang berbeda (`distinct('name')`),
     * bukan jumlah baris -- karena tiap nama diulang sekali untuk tiap
     * katalog. Jadi angkanya sama dengan yang terlihat di tabel paket
     * (lima nama tier resmi), bukan 500 baris.
     *
     * @return array<string, int>
     */
    private function counts(): array
    {
        return Cache::remember('admin_stats_counts', 60, function (): array {
            return [
                'catalogs' => Catalog::query()->count(),
                'packages' => Package::query()->distinct()->count('name'),
                'categories' => Category::query()->count(),
                'users' => User::query()->count(),
                'admins' => Admin::query()->count(),
                'reviews' => Review::query()->count(),
                'reports' => Report::query()->count(),
                'reference_options' => ReferenceOption::query()->count(),
                'helps' => Help::query()->count(),
                'policies' => PrivacyPolicy::query()->count()
                    + TermsOfService::query()->count()
                    + WeddingDecorationPolicy::query()->count(),

                'orders' => Order::query()->count(),
                'unpaid_orders' => Order::query()
                    ->where('payment_status', OrderPaymentStatus::UNPAID->value)
                    ->count(),
                'transactions' => Transaction::query()->count(),
                'vouchers' => Voucher::query()->count(),
                'discounts' => Discount::query()->count(),
                'payment_methods' => PaymentMethod::query()->count(),
                'payment_gateways' => PaymentGateway::query()->count(),
                'wishlists' => Wishlist::query()->count(),
            ];
        });
    }
}
