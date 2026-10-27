<?php

namespace Tests\Feature\Admin\AdminStatsOverviewTest;

use App\Filament\Admin\Widgets\StatsOverview\StatsOverview;
use App\Models\Catalog\Catalog;
use App\Models\Category\Category;
use App\Models\Package\Package;
use App\Models\User\User;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Widget ringkasan angka di dashboard panel admin.
 *
 * Dua hal yang dijaga:
 *
 *   1. **LENGKAP & urut Data Master dulu.** Dashboard adalah tempat admin
 *      pertama kali menoleh; isi toko (Data Master) harus dibaca lebih dulu
 *      daripada transaksinya.
 *
 *   2. **Tidak ada campur bahasa.** Label & deskripsi diambil dengan
 *      `__()`. Kalau kuncinya tidak ada di `lang/id.json` DAN
 *      `lang/en.json`, `AutoTranslator` ikut menerjemahkan -- jadi di
 *      Bahasa Inggris judulnya Bahasa Inggris tapi deskripsinya Bahasa
 *      Indonesia (atau sebaliknya).
 */
class AdminStatsOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Angka widget di-cache (satu kunci untuk semua statistik). Tanpa
        // flush, test kedua menerima angka dari test pertama.
        Cache::forget('admin_stats_counts');
    }

    /**
     * @return array<int, Stat>
     */
    private function stats(): array
    {
        $widget = new StatsOverview;

        $method = new \ReflectionMethod($widget, 'getStats');
        $method->setAccessible(true);

        return $method->invoke($widget);
    }

    public function test_it_lists_every_statistic_with_master_data_first(): void
    {
        $stats = $this->stats();

        $this->assertGreaterThanOrEqual(18, count($stats), 'Statistiknya harus lengkap.');

        $labels = array_map(fn (Stat $stat): string => (string) $stat->getLabel(), $stats);

        // sepuluh pertama = Data Master
        $masterData = array_slice($labels, 0, 10);

        foreach ([
            'Total Produk', 'Total Paket', 'Total Kategori', 'Total Pengguna',
            'Total Admin Tim', 'Total Ulasan', 'Total Laporan', 'Opsi Referensi',
            'Pusat Bantuan', 'Total Kebijakan',
        ] as $expected) {
            $this->assertContains($expected, $masterData, "Statistik Data Master '{$expected}' tidak ada.");
        }

        // sisanya = Transaksi
        $transactions = array_slice($labels, 10);

        foreach ([
            'Total Pesanan', 'Total Transaksi', 'Total Voucher', 'Total Diskon',
            'Metode Pembayaran', 'Payment Gateway', 'Total Wishlist',
        ] as $expected) {
            $this->assertContains($expected, $transactions, "Statistik Transaksi '{$expected}' tidak ada.");
        }
    }

    public function test_every_label_and_description_has_a_translation_in_both_locales(): void
    {
        $id = json_decode((string) file_get_contents(lang_path('id.json')), true);
        $en = json_decode((string) file_get_contents(lang_path('en.json')), true);

        foreach ($this->stats() as $stat) {
            foreach ([$stat->getLabel(), $stat->getDescription()] as $text) {
                $text = (string) $text;

                if ($text === '') {
                    continue;
                }

                $this->assertArrayHasKey($text, $id, "Kunci '{$text}' tidak ada di lang/id.json.");
                $this->assertArrayHasKey($text, $en, "Kunci '{$text}' tidak ada di lang/en.json.");
            }
        }
    }

    public function test_the_numbers_match_the_database(): void
    {
        $values = [];

        foreach ($this->stats() as $stat) {
            $values[(string) $stat->getLabel()] = (string) $stat->getValue();
        }

        $this->assertSame((string) User::query()->count(), $values['Total Pengguna']);
        $this->assertSame((string) Catalog::query()->count(), $values['Total Produk']);
        $this->assertSame((string) Category::query()->count(), $values['Total Kategori']);

        // Paket dihitung dari NAMA NYA yang berbeda -- sama dengan yang
        // terlihat di tabel paket (lima nama tier), bukan 500 baris.
        $this->assertSame(
            (string) Package::query()->distinct()->count('name'),
            $values['Total Paket'],
        );
    }

    /**
     * Setiap kartu HARUS bisa diklik dan menuju halaman admin yang relevan.
     *
     * Peta label -> path ini sengaja ditulis eksplisit, bukan dihitung dari
     * nama resource: "Total Paket" dan "Total Kategori" tidak punya resource
     * sendiri (keduanya RelationManager di halaman katalog), dan "Total
     * Kebijakan" adalah gabungan tiga dokumen. Kalau suatu saat resource baru
     * dibuat, test ini yang memaksa peta di widget ikut diperbarui.
     */
    public function test_every_card_links_to_its_admin_page(): void
    {
        $expected = [
            'Total Produk' => '/admin/catalogs',
            'Total Paket' => '/admin/catalogs',
            'Total Kategori' => '/admin/catalogs',
            'Total Pengguna' => '/admin/users',
            'Total Admin Tim' => '/admin/admins',
            'Total Ulasan' => '/admin/reviews',
            'Total Laporan' => '/admin/reports',
            'Opsi Referensi' => '/admin/reference-options',
            'Pusat Bantuan' => '/admin/helps',
            'Total Kebijakan' => '/admin/privacy-policies',
            'Total Pesanan' => '/admin/orders',
            'Belum dibayar' => '/admin/orders',
            'Total Transaksi' => '/admin/transactions',
            'Total Voucher' => '/admin/vouchers',
            'Total Diskon' => '/admin/discounts',
            'Metode Pembayaran' => '/admin/payment-methods',
            'Payment Gateway' => '/admin/payment-gateways',
            'Total Wishlist' => '/admin/wishlists',
        ];

        foreach ($this->stats() as $stat) {
            $label = (string) $stat->getLabel();

            $this->assertArrayHasKey($label, $expected, "Kartu '{$label}' belum punya tujuan klik.");

            $url = $stat->getUrl();

            $this->assertNotNull($url, "Kartu '{$label}' tidak punya URL.");
            $this->assertSame(url($expected[$label]), $url, "Kartu '{$label}' menuju halaman yang salah.");
        }
    }

    /**
     * Di laptop (>= 1280px) grid statistik harus ENAM KOLOM, jadi 18 kartu
     * = TIGA BARIS PENUH.
     *
     * Dengan lima kolom, baris terakhir cuma berisi tiga kartu -- grid-nya
     * terlihat setengah kosong. Aturannya ada di
     * `resources/css/Shared/Shared.css` (`@media (min-width: 1280px)`).
     */
    public function test_the_laptop_grid_is_six_columns_so_eighteen_cards_make_three_rows(): void
    {
        $css = (string) file_get_contents(resource_path('css/Shared/Shared.css'));

        $this->assertMatchesRegularExpression(
            '/@media\s*\(min-width:\s*1280px\)\s*\{[^}]*\.fi-wi-stats-overview-stats-ctn\s*\{[^}]*repeat\(6,/',
            $css,
            'Grid statistik di laptop harus 6 kolom.',
        );

        $count = count($this->stats());

        $this->assertSame(0, $count % 6, 'Jumlah kartu harus habis dibagi 6 -- supaya baris terakhir penuh.');
    }
}
