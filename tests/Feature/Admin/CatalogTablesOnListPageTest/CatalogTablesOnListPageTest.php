<?php

use App\Filament\Admin\Resources\CatalogResource\Pages\ListCatalogs\ListCatalogs;
use App\Livewire\Admin\CatalogTables\CategoryTable;
use App\Livewire\Admin\CatalogTables\PackageTable;
use App\Models\Admin\Admin;
use App\Models\Catalog\Catalog;
use App\Models\Category\Category;
use App\Models\Package\Package;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

/**
 * Halaman daftar katalog punya TIGA tabel -- satu per satu.
 *
 *   - `/admin/catalogs`                 -> tabel katalog (default)
 *   - `/admin/catalogs?tab=category`    -> tabel kategori
 *   - `/admin/catalogs?tab=package`     -> tabel paket
 *
 * Switcher-nya di sub-navigasi sidebar halaman.
 *
 * Kenapa ini perlu diuji sebagai satu Rendering, bukan per komponen:
 *
 *   Komponennya bisa hidup dan sehat satu per satu, tapi tidak muncul sama
 *   sekali di halaman. Hook render yang salah sasaran (mis. dipasang di panel
 *   yang salah, atau `instanceof`-nya tidak cocok) menghasilkan halaman yang
 *   200 dengan satu tabel saja -- dan tidak ada error, tidak ada stack trace,
 *   tidak ada apa pun yang gagal. Cuma terlihat oleh manusia yang tahu harusnya
 *   tiga tabel.
 */
beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'admin']);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->admin = Admin::factory()->create();
    $this->admin->assignRole('super_admin');
});

test('halaman daftar katalog merender tabel katalog secara default', function (): void {
    $category = Category::create(['name' => 'Kategori Uji']);
    $catalog = Catalog::create([
        'name' => 'Katalog Uji',
        'slug' => 'katalog-uji',
        'category_id' => $category->id,
        'price' => 1_000_000,
        'stock' => 1,
    ]);
    Package::create([
        'name' => 'Paket Standar',
        'slug' => 'katalog-uji-standard',
        'catalog_id' => $catalog->id,
        'price' => 1_000_000,
        'stock' => 5,
    ]);

    $html = actingAs($this->admin, 'admin')
        ->get(ListCatalogs::getUrl())
        ->assertSuccessful()
        ->getContent();

    // Tanpa `?tab=` yang tampil adalah tabel katalog -- dua tabel global
    // TIDAK ikut di halaman yang sama.
    expect($html)->toContain('Katalog Uji');
    expect($html)->not->toContain('fm-admin-catalog-categories');
    expect($html)->not->toContain('fm-admin-catalog-packages');
});

test('tab kategori dan tab paket merender tabelnya', function (): void {
    $category = Category::create(['name' => 'Kategori Uji']);
    $catalog = Catalog::create([
        'name' => 'Katalog Uji',
        'slug' => 'katalog-uji',
        'category_id' => $category->id,
        'price' => 1_000_000,
        'stock' => 1,
    ]);
    Package::create([
        'name' => 'Paket Standar',
        'slug' => 'katalog-uji-standard',
        'catalog_id' => $catalog->id,
        'price' => 1_000_000,
        'stock' => 5,
    ]);

    $kategori = actingAs($this->admin, 'admin')
        ->get(ListCatalogs::getUrl(['tab' => 'category']))
        ->assertSuccessful()
        ->getContent();

    expect($kategori)->toContain('fm-admin-catalog-categories');
    expect($kategori)->toContain('Kategori Uji');
    expect($kategori)->not->toContain('fm-admin-catalog-packages');

    $paket = actingAs($this->admin, 'admin')
        ->get(ListCatalogs::getUrl(['tab' => 'package']))
        ->assertSuccessful()
        ->getContent();

    expect($paket)->toContain('fm-admin-catalog-packages');
    expect($paket)->toContain('Paket Standar');
    expect($paket)->not->toContain('fm-admin-catalog-categories');
});

test('tabel kategori dan paket tidak muncul di halaman daftar lain', function (): void {
    $html = actingAs($this->admin, 'admin')
        ->get(\App\Filament\Admin\Resources\OrderResource\OrderResource::getUrl('index'))
        ->assertSuccessful()
        ->getContent();

    // Panel admin pakai SPA navigation, jadi hook render global akan ikut
    // muncul di setiap halaman daftar. Inilah yang memastikan tidak semua
    // halaman daftar ikut mendapat dua tabel ini.
    expect($html)->not->toContain('fm-admin-catalog-categories');
    expect($html)->not->toContain('fm-admin-catalog-packages');
});

test('tabel kategori dan paket disembunyikan dari admin tanpa view_catalog', function (): void {
    // Role non-admin yang tidak punya permission katalog sama sekali.
    $role = Role::firstOrCreate(['name' => 'tanpa_katalog', 'guard_name' => 'admin']);

    $limited = Admin::factory()->create();
    $limited->assignRole($role);

    $html = actingAs($limited, 'admin')
        ->get(ListCatalogs::getUrl(['tab' => 'category']))
        ->getContent();

    expect($html)->not->toContain('fm-admin-catalog-categories');
});

test('komponen tabel kategori bisa dipakai dan ter-query', function (): void {
    Category::create(['name' => 'Bunga Melati']);

    Livewire::actingAs($this->admin, 'admin')
        ->test(CategoryTable::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([Category::where('name', 'Bunga Melati')->first()]);
});

/**
 * Tabel paket hanya punya SATU kolom: nama.
 *
 * Tujuh kolom lain (Urutan, Katalog, Kategori, Harga, Harga Diskon, Stok,
 * Aktif) dihapus karena isinya milik katalog dan sudah bisa dilihat dari form
 * Catalog. Tes ini mengunci supaya tidak ada yang menambah kolom lagi tanpa
 * sadar bahwa tabel ini adalah daftar nama.
 */
test('komponen tabel paket hanya menampilkan nama', function (): void {
    $package = Package::create([
        'name' => 'Paket Lengkap (All-in-One)',
        'slug' => 'paket-lengkap-uji',
        'price' => 2_000_000,
        'stock' => 4,
    ]);

    Livewire::actingAs($this->admin, 'admin')
        ->test(PackageTable::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$package])
        ->assertTableColumnStateSet('name', 'Paket Lengkap (All-in-One)', $package);

    // Kolom-kolom yang sudah dihapus tidak boleh muncul lagi.
    Livewire::actingAs($this->admin, 'admin')
        ->test(PackageTable::class)
        ->assertTableColumnDoesNotExist('sort_order')
        ->assertTableColumnDoesNotExist('catalog.name')
        ->assertTableColumnDoesNotExist('category.name')
        ->assertTableColumnDoesNotExist('price')
        ->assertTableColumnDoesNotExist('stock')
        ->assertTableColumnDoesNotExist('is_active');
});

/**
 * Tabel paket = SATU BARIS PER NAMA, bukan satu baris per record.
 *
 * Di database ada 500 baris paket tapi hanya LIMA nama (lima tier resmi),
 * masing-masing diulang sekali untuk tiap katalog. Kalau tabelnya
 * menampilkan semuanya, setiap baris terlihat sama persis -- teksnya
 * terduplikasi 100 kali.
 */
test('tabel paket menampilkan satu baris per nama paket', function (): void {
    $catalog = Catalog::create([
        'name' => 'Katalog Uji',
        'slug' => 'katalog-uji-paket',
        'category_id' => Category::create(['name' => 'Kategori Uji'])->id,
        'price' => 1_000_000,
        'stock' => 1,
    ]);

    // Tiga katalog, masing-masing dengan paket bernama sama.
    foreach (['Paket Standar', 'Paket Sedang'] as $index => $name) {
        foreach (range(1, 3) as $copy) {
            Package::create([
                'name' => $name,
                'slug' => 'paket-'.($index * 10 + $copy),
                'catalog_id' => $catalog->id,
                'price' => 1_000_000,
                'stock' => 5,
            ]);
        }
    }

    // Enam record, tapi hanya DUA nama.
    expect(Package::query()->count())->toBe(6);

    $component = Livewire::actingAs($this->admin, 'admin')
        ->test(PackageTable::class)
        ->assertSuccessful();

    // Tabelnya hanya dua baris -- satu per nama. Urutannya "Urutan Tampil"
    // dulu (di sini keduanya kosong), lalu nama.
    expect($component->instance()->getTableRecords())->toHaveCount(2);
    expect($component->instance()->getTableRecords()->pluck('name')->all())
        ->toBe(['Paket Sedang', 'Paket Standar']);
});

/**
 * Tabel kategori juga hanya punya satu kolom: nama.
 *
 * Dulu ada "Dipakai Catalog" dan "Dipakai Paket" -- dihapus karena angka
 * "dipakai di mana" milik halaman katalog, bukan milik daftar nama ini.
 */
test('komponen tabel kategori hanya menampilkan nama', function (): void {
    $category = Category::create(['name' => 'Kategori Uji Nama']);

    Livewire::actingAs($this->admin, 'admin')
        ->test(CategoryTable::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$category])
        ->assertTableColumnStateSet('name', 'Kategori Uji Nama', $category);
});
