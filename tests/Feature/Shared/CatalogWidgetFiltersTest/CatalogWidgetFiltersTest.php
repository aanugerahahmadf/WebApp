<?php

use App\Filament\Welcome\Widgets\CombinedCatalogWidget\CombinedCatalogWidget;
use App\Models\Catalog\Catalog;
use App\Models\Category\Category;
use App\Models\User\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Filter katalog adalah public property Livewire milik widget, bukan filter
 * tabel Filament: grid-nya dirender lewat `Table::content()`, yang tidak pernah
 * membaca filter tabel. Test ini mengunci jalur binding yang sebenarnya dipakai
 * browser (`wire:model` -> property -> grid).
 */
test('the welcome catalog widget renders and filters through livewire', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('welcome'));

    $user = User::factory()->create(['email_verified_at' => now()]);
    actingAs($user, 'web');

    $category = Category::create(['name' => 'Buket Bunga']);

    Catalog::create([
        'category_id' => $category->id,
        'name' => 'Buket Mawar Putih',
        'slug' => 'buket-mawar-putih',
        'description' => 'Buket sederhana',
        'price' => 900_000,
        'stock' => 5,
        'is_active' => true,
        'is_featured' => true,
    ]);

    Catalog::create([
        'category_id' => $category->id,
        'name' => 'Dekorasi Meja',
        'slug' => 'dekorasi-meja',
        'description' => 'Dekorasi meja',
        'price' => 6_000_000,
        'stock' => 2,
        'is_active' => true,
    ]);

    Livewire::test(CombinedCatalogWidget::class)
        ->assertSee('Filter')
        ->assertSee('Buket Mawar Putih')
        ->assertSee('Dekorasi Meja')
        // Kartu: rating berupa bintang (bukan angka) dan badge unggulan tidak
        // boleh memakai ikon path Filament yang dirender sebagai <img> tanpa
        // ukuran -- itu yang dulu membuat badge melebar jadi lingkaran pink.
        ->assertSee('catalog-star', escape: false)
        ->assertSee('catalog-badge-featured', escape: false)
        ->assertSee('catalog-badge-icon', escape: false)
        ->assertDontSee('images/icons/sparkles.svg', escape: false)
        // Rating minimum dan urutan tidak boleh memakai <select> bawaan
        // (popup-nya lancip) dan tidak boleh menampilkan angka threshold.
        ->assertSee('catalog-filter-chip-rating', escape: false)
        ->assertSee('catalog-sort-menu', escape: false)
        ->assertDontSee('<select wire:model.live="catalogSort"', escape: false)
        ->assertDontSee('3.0+')
        ->assertDontSee('4.5+')
        ->set('catalogMinRating', '4.5')
        ->assertSee('catalog-filter-chip-rating is-active', escape: false)
        // Chip aktif ikut menampilkan bintang, bukan "Rating 4.5+".
        ->assertSee('catalog-active-chip', escape: false)
        ->assertDontSee('Rating 4.5+')
        ->set('catalogMinRating', '')
        ->set('catalogSearch', 'mawar')
        ->assertSee('Buket Mawar Putih')
        ->assertDontSee('Dekorasi Meja')
        ->assertSee('Filter aktif:')
        ->set('catalogSearch', '')
        ->set('catalogCategoryIds', [$category->id])
        ->assertSee('Buket Mawar Putih')
        ->set('catalogDiscountOnly', true)
        ->assertSee('Tidak ada katalog yang cocok')
        ->call('resetCatalogFilters')
        ->assertSee('Buket Mawar Putih')
        ->assertSee('Dekorasi Meja');
});

test('the sort dropdown only accepts known sort keys and resets paging', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('welcome'));

    actingAs(User::factory()->create(['email_verified_at' => now()]), 'web');

    Livewire::test(CombinedCatalogWidget::class)
        ->set('catalogPerPage', 48)
        ->call('setCatalogSort', 'price_low')
        ->assertSet('catalogSort', 'price_low')
        ->assertSet('catalogPerPage', 12)
        ->call('setCatalogSort', 'bukan-urutan')
        ->assertSet('catalogSort', 'price_low');
});
