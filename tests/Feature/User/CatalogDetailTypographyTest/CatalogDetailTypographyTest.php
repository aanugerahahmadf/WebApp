<?php

use App\Filament\User\Resources\CatalogResource\Pages\ViewCatalog\ViewCatalog;
use App\Models\Catalog\Catalog;
use App\Models\FmAppSetting\FmAppSetting;
use App\Models\User\User;
use App\Support\PageStyles\PageStyles;
use App\Support\TextStyles\TextStyles;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/*
 * HALAMAN DETAIL KATALOG (/flowerdecorationscatalog/{id}) BER-SCOPE.
 *
 * Seluruh halaman katalog publik membawa induk
 * `data-page-style="catalogs"`; di dalamnya:
 *
 *   - nama katalog: data-typo="heading" + data-field="catalogs.name"
 *   - deskripsi:    data-typo="description" + data-field="catalogs.description"
 *   - harga:        data-field="catalogs.price" (komponen catalog-price)
 *
 * Custom admin dari scope `catalogs` tampil DI SINI; dan karena semua
 * CSS ber-induk [data-page-style="catalogs"] -- custom halaman LAIN
 * tidak pernah menyentuh halaman ini, and custom halaman ini tidak
 * pernah menyentuh halaman lain.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    seedRoles(adminRoles: ['super_admin'], webRoles: ['super_admin', 'customer']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');

    $this->customer = User::factory()->create();
    $this->customer->assignRole('customer');

    Filament::setCurrentPanel(Filament::getPanel('user'));

    FmAppSetting::query()->whereIn('key', ['page_scope_styles', 'text_styles'])->delete();
    PageStyles::forgetCache();
    TextStyles::forgetCache();
});

afterEach(function () {
    FmAppSetting::query()->whereIn('key', ['page_scope_styles', 'text_styles'])->delete();
    PageStyles::forgetCache();
    TextStyles::forgetCache();
});

test('detail katalog membawa penanda halaman dan atribut teks pada nama dan deskripsi', function () {
    actingAs($this->customer);

    $catalog = Catalog::factory()->create([
        'name_translations' => ['id' => 'Produk Bunga Mewah', 'en' => 'Luxury Flower Product'],
        'features'          => ['Buket segar', 'Dibuat custom'],
    ]);

    $html = Livewire::test(ViewCatalog::class, ['record' => $catalog->id])
        ->assertSuccessful()
        ->html();

    expect($html)->toContain('data-page-style="catalogs"')
        ->and($html)->toContain('data-typo="heading"')
        ->and($html)->toContain('data-field="catalogs.name"')
        ->and($html)->toContain('data-typo="description"')
        ->and($html)->toContain('data-field="catalogs.description"')
        ->and($html)->toContain('data-field="catalogs.price"');
});

test('custom scope katalog tampil di detail dan TIDAK tampil di scope lain', function () {
    actingAs($this->customer);

    PageStyles::apply('catalogs', ['heading_transform' => 'uppercase']);
    TextStyles::apply('catalogs', 'catalogs', 'name', ['size' => 32]);

    $catalog = Catalog::factory()->create([
        'name_translations' => ['id' => 'Produk Bunga Mewah', 'en' => 'Luxury Flower Product'],
    ]);

    // HTML halaman memuat penanda halaman + field.
    $html = Livewire::test(ViewCatalog::class, ['record' => $catalog->id])->html();

    expect($html)->toContain('data-page-style="catalogs"')
        ->and($html)->toContain('data-typo="heading"')
        ->and($html)->toContain('data-field="catalogs.name"');

    // Stylesheet yang AKAN disuntik panel memuat aturan HANYA untuk
    // scope catalogs -- tidak ada selector scope lain yang bocor.
    $styles = App\Support\AppFont\AppFont::styles();

    expect($styles)->toContain('[data-page-style="catalogs"] [data-typo="heading"]')
        ->toContain('text-transform: uppercase !important;')
        ->toContain('[data-page-style="catalogs"] [data-field="catalogs.name"]')
        ->and(str_contains($styles, '[data-page-style="home"]'))->toBeFalse();
});

test('kartu grid katalog membawa atribut data-field per kolom + chrome border', function () {
    $catalog = Catalog::factory()->create([
        'name_translations' => ['id' => 'Produk Bunga Mewah', 'en' => 'Luxury Flower Product'],
    ]);

    $html = view('User.components.catalog-grid.catalog-grid', [
        'records' => collect([$catalog]),
    ])->render();

    expect($html)->toContain('data-field="catalogs.name"')
        ->and($html)->toContain('data-field="catalogs.price"')
        ->and($html)->toContain('data-chrome="catalogs.border"');
});

test('tiga sumber atribut selalu konsisten menarget kolom tabel catalogs', function (): void {
    // Anti-drift: fieldKey selalu dari kombinasi tabel+kolom yang sama.
    $expected = [
        TextStyles::fieldKey(( new Catalog)->getTable(), 'name'),
        TextStyles::fieldKey(( new Catalog)->getTable(), 'description'),
        TextStyles::fieldKey(( new Catalog)->getTable(), 'price'),
    ];

    expect($expected)->toBe(['catalogs.name', 'catalogs.description', 'catalogs.price']);
});
