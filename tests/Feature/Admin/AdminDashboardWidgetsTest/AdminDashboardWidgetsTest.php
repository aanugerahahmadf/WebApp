<?php

use App\Filament\Admin\Widgets\OrdersChart\OrdersChart;
use App\Filament\Admin\Widgets\RecentOrders\RecentOrders;
use App\Filament\Admin\Widgets\RevenueChart\RevenueChart;
use App\Filament\Admin\Widgets\StatsOverview\StatsOverview;
use App\Filament\Admin\Widgets\StoreProgressRoadmap\StoreProgressRoadmap;
use App\Models\Admin\Admin;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

/**
 * Widget dashboard panel admin.
 *
 * Yang dikunci di sini:
 *
 *   1. Semua widget dashboard LEBAR PENUH. Default `Widget::$columnSpan`
 *      = 1, jadi widget yang cuma occupy separuh lebar halaman ->
 *      grid 4 kolom di dalamnya sempit sampai kartu-kartunya terpotong.
 *   2. Label stat memakai kunci yang ADA di `lang/id.json` DAN
 *      `lang/en.json`. Kalau kuncinya baru, `__()` autoc-translate ->
 *      judul Bahasa Inggris, deskripsi Bahasa Indonesia, di layar yang sama.
 *   3. Grafik punya tinggi maksimum, supaya tidak jadi kotak kosong raksasa.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'admin']);

    $this->admin = Admin::factory()->create(['active_status' => true]);
    $this->admin->assignRole('super_admin');

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('semua widget dashboard occupy lebar penuh', function (string $widget): void {
    $instance = new $widget;

    expect((string) $instance->getColumnSpan())->toBe('full');
})->with([
    StoreProgressRoadmap::class,
    StatsOverview::class,
    RevenueChart::class,
    OrdersChart::class,
    RecentOrders::class,
]);

it('grafik punya tinggi maksimum supaya tidak jadi kotak kosong raksasa', function (string $widget): void {
    // `getMaxHeight()` di Filamentprotected, jadi dibaca lewat reflection.
    $method = new ReflectionMethod($widget, 'getMaxHeight');
    $method->setAccessible(true);

    expect($method->invoke(new $widget))->not->toBeNull();
})->with([
    [RevenueChart::class],
    [OrdersChart::class],
]);

it('label stat memakai kunci yang ada di dua bahasa', function (): void {
    $en = json_decode((string) file_get_contents(lang_path('en.json')), true);
    $id = json_decode((string) file_get_contents(lang_path('id.json')), true);

    foreach ([
        'Total Pengguna',
        'Total Produk',
        'Total Pesanan',
        'Ulasan Pelanggan',
        'Terdaftar di aplikasi',
        'Produk aktif',
        'Masuk dashboard',
        'Dari pelanggan',
    ] as $key) {
        expect(array_key_exists($key, $en))->toBeTrue("Kunci `{$key}` tidak ada di en.json");
        expect(array_key_exists($key, $id))->toBeTrue("Kunci `{$key}` tidak ada di id.json");
    }
});

it('dashboard merender label stat tanpa campuran dua bahasa', function (): void {
    actingAs($this->admin, 'admin');

    $html = $this->get('/admin/home')->assertSuccessful()->getContent();

    // Judul stat (dari kunci yang diterjemahkan) ikut bahasa yang aktif.
    expect($html)->toContain(__('Total Pengguna'))
        ->and($html)->toContain(__('Total Produk'))
        ->and($html)->toContain(__('Ulasan Pelanggan'))
        // Deskripsinya juga ikut bahasa yang sama.
        ->and($html)->toContain(__('Terdaftar di aplikasi'));
});

it('setiap kartu statistik adalah tautan ke halaman admin yang relevan', function (): void {
    actingAs($this->admin, 'admin');

    $html = $this->get('/admin/home')->assertSuccessful()->getContent();

    // 18 kartu, semuanya harus dirender sebagai <a>, bukan <div>.
    preg_match_all('/<a\b[^>]*fi-wi-stats-overview-stat/', $html, $matches);

    expect($matches[0])->toHaveCount(18);

    foreach ([
        '/admin/catalogs',
        '/admin/users',
        '/admin/admins',
        '/admin/reviews',
        '/admin/reports',
        '/admin/reference-options',
        '/admin/helps',
        '/admin/privacy-policies',
        '/admin/orders',
        '/admin/transactions',
        '/admin/vouchers',
        '/admin/discounts',
        '/admin/payment-methods',
        '/admin/payment-gateways',
        '/admin/wishlists',
    ] as $path) {
        expect($html)->toContain('href="'.url($path).'"');
    }
});
