<?php

use App\Filament\Admin\Resources\CatalogResource\CatalogResource;
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
use function Pest\Laravel\get;

/**
 * SUB-NAVIGASI katalog: Category, Packages, Catalog.
 *
 * Satu halaman daftar katalog, tiga tabel, dipilih lewat query param
 * `?tab=` (category / package / catalog). Switcher-nya ada di sub-navigasi
 * sidebar halaman itu.
 *
 * Yang diuji di sini bukan cuma "halamannya hidup" (itu bisa hidup tanpa
 * sub-navigasi sama sekali), tapi DAFTAR sub-navigasi-nya -- karena
 * sub-navigasi yang kosong cuma kelihatan kalau orang yang tahu harusnya
 * ada tiga item.
 */
beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'admin']);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->admin = Admin::factory()->create();
    $this->admin->assignRole('super_admin');
});

test('sub-navigasi katalog punya tiga item: kategori, paket, katalog', function (): void {
    $items = (new ListCatalogs)->getSubNavigation();

    expect($items)->toHaveCount(3);
    expect($items[0]->getLabel())->toBe(__('Kategori'));
    expect($items[1]->getLabel())->toBe(__('Paket'));
    expect($items[2]->getLabel())->toBe(__('Katalog'));

    expect($items[0]->getUrl())->toContain('tab=category');
    expect($items[1]->getUrl())->toContain('tab=package');
    expect($items[2]->getUrl())->not->toContain('tab=');
});

test('sub-navigasi katalog tampil di sidebar halaman daftar katalog', function (): void {
    $html = actingAs($this->admin, 'admin')
        ->get(ListCatalogs::getUrl())
        ->assertSuccessful()
        ->getContent();

    // Posisi `Start` = sub-navigasi dirender sebagai kolom di samping konten
    // (sidebar), bukan tab di atas.
    expect($html)->toContain('fi-page-sub-navigation-sidebar');

    expect($html)->toContain(__('Kategori'));
    expect($html)->toContain(__('Paket'));
    expect($html)->toContain(__('Katalog'));
});

test('halaman daftar katalog menampilkan tabel katalog secara default', function (): void {
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

    // Tanpa `?tab=` yang tampil adalah tabel katalog.
    expect($html)->toContain('Katalog Uji');
});

test('tab=kategori dan tab=paket menampilkan tabelnya', function (): void {
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

    $paket = actingAs($this->admin, 'admin')
        ->get(ListCatalogs::getUrl(['tab' => 'package']))
        ->assertSuccessful()
        ->getContent();

    expect($paket)->toContain('fm-admin-catalog-packages');
    expect($paket)->toContain('Paket Standar');
});

test('halaman daftar lain di panel admin tidak mendapat tabel katalog', function (): void {
    $html = actingAs($this->admin, 'admin')
        ->get(\App\Filament\Admin\Resources\OrderResource\OrderResource::getUrl('index'))
        ->assertSuccessful()
        ->getContent();

    expect($html)->not->toContain('fm-admin-catalog-categories');
    expect($html)->not->toContain('fm-admin-catalog-packages');
});

test('tabel kategori disembunyikan dari admin tanpa view_catalog', function (): void {
    $role = Role::firstOrCreate(['name' => 'tanpa_katalog', 'guard_name' => 'admin']);

    $limited = Admin::factory()->create();
    $limited->assignRole($role);

    $html = actingAs($limited, 'admin')
        ->get(ListCatalogs::getUrl(['tab' => 'category']))
        ->getContent();

    expect($html)->not->toContain('fm-admin-catalog-categories');
});

/**
 * Komponen tabelnya sendiri harus tetap utuh -- sub-navigasi cuma
 * memindahkannya ke tab lain, bukan mengubah perilakunya.
 */
test('komponen tabel kategori dan paket masih bisa dipakai', function (): void {
    $category = Category::create(['name' => 'Bunga Melati']);

    Livewire::actingAs($this->admin, 'admin')
        ->test(CategoryTable::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$category]);

    Livewire::actingAs($this->admin, 'admin')
        ->test(PackageTable::class)
        ->assertSuccessful();
});
