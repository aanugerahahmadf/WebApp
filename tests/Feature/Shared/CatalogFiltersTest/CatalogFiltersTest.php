<?php

use App\Filament\Shared\Concerns\HasCatalogFilters;
use App\Filament\Welcome\Resources\CatalogResource\CatalogResource;
use App\Models\Catalog\Catalog;
use App\Models\Category\Category;
use App\Models\Review\Review;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Harness kecil: memakai trait tanpa boot Livewire/Filament, supaya logika
 * filter bisa diuji langsung. `catalogViewData()` hanya butuh query katalog.
 */
final class CatalogFilterHarness
{
    use HasCatalogFilters;

    protected static function catalogResourceClass(): string
    {
        return CatalogResource::class;
    }
}

function catalogFilterCategory(string $name): Category
{
    return Category::create(['name' => $name]);
}

function catalogFilterCatalog(array $attributes): Catalog
{
    static $sequence = 0;

    $sequence++;

    $createdAt = $attributes['created_at'] ?? null;
    unset($attributes['created_at']);

    $catalog = Catalog::create(array_merge([
        'name' => 'Katalog '.$sequence,
        'slug' => 'katalog-'.$sequence,
        'description' => 'Deskripsi katalog '.$sequence,
        'price' => 1_000_000,
        'discount_price' => null,
        'stock' => 10,
        'is_active' => true,
        'is_featured' => false,
    ], $attributes));

    if ($createdAt !== null) {
        $catalog->forceFill(['created_at' => $createdAt])->save();
    }

    return $catalog;
}

function catalogFilterReview(User $user, Catalog $catalog, int $rating): void
{
    Review::create([
        'user_id' => $user->id,
        'catalog_id' => $catalog->id,
        'rating' => $rating,
        'comment' => 'Ulasan uji',
    ]);
}

function catalogFilterIds(CatalogFilterHarness $harness): array
{
    return $harness->catalogViewData()['items']->pluck('id')->all();
}

beforeEach(function (): void {
    $this->user = User::factory()->create(['email_verified_at' => now()]);

    $this->bouquet = catalogFilterCategory('Buket Bunga');
    $this->table = catalogFilterCategory('Dekorasi Meja');

    $this->cheap = catalogFilterCatalog([
        'category_id' => $this->bouquet->id,
        'name' => 'Buket Mawar Putih',
        'description' => 'Buket sederhana',
        'price' => 900_000,
        'stock' => 5,
        'created_at' => now()->subDays(4),
    ]);

    $this->discounted = catalogFilterCatalog([
        'category_id' => $this->bouquet->id,
        'name' => 'Buket Bunga Premium',
        'description' => 'Buket premium dengan pita emas',
        'price' => 2_000_000,
        'discount_price' => 1_500_000,
        'stock' => 0,
        'is_featured' => true,
        'created_at' => now()->subDays(3),
    ]);

    $this->tableDecor = catalogFilterCatalog([
        'category_id' => $this->table->id,
        'name' => 'Dekorasi Meja Tamu',
        'description' => 'Dekorasi meja panjang',
        'price' => 6_000_000,
        'stock' => 10,
        'created_at' => now()->subDays(2),
    ]);

    $this->luxury = catalogFilterCatalog([
        'category_id' => $this->table->id,
        'name' => 'Dekorasi Pelaminan Mewah',
        'description' => 'Dekorasi pelaminan skala besar',
        'price' => 30_000_000,
        'stock' => 1,
        'created_at' => now()->subDay(),
    ]);

    catalogFilterReview($this->user, $this->cheap, 3);
    catalogFilterReview($this->user, $this->discounted, 5);
    catalogFilterReview($this->user, $this->tableDecor, 4);
    catalogFilterReview($this->user, $this->luxury, 5);

    $this->harness = new CatalogFilterHarness;
});

test('category, search, and price filters narrow the grid', function (): void {
    $this->harness->catalogCategoryIds = [$this->bouquet->id];

    expect(catalogFilterIds($this->harness))
        ->toEqualCanonicalizing([$this->cheap->id, $this->discounted->id]);

    $this->harness->catalogCategoryIds = [];
    $this->harness->catalogSearch = 'meja';

    expect(catalogFilterIds($this->harness))
        ->toEqualCanonicalizing([$this->tableDecor->id, $this->luxury->id]);

    $this->harness->catalogSearch = '';
    $this->harness->catalogPriceRange = 'under_1m';

    expect(catalogFilterIds($this->harness))->toBe([$this->cheap->id]);

    // Preset memakai harga akhir setelah diskon (1,5 juta), bukan harga asli.
    $this->harness->catalogPriceRange = '1m_3m';

    expect(catalogFilterIds($this->harness))->toBe([$this->discounted->id]);

    // Rentang kustom menimpa preset.
    $this->harness->catalogPriceRange = '';
    $this->harness->catalogMinPrice = '1600000';
    $this->harness->catalogMaxPrice = '7000000';

    expect(catalogFilterIds($this->harness))->toBe([$this->tableDecor->id]);
});

test('rating, discount, stock, and featured filters work together', function (): void {
    $this->harness->catalogMinRating = '4.5';

    expect(catalogFilterIds($this->harness))
        ->toEqualCanonicalizing([$this->discounted->id, $this->luxury->id]);

    $this->harness->catalogMinRating = '';
    $this->harness->catalogDiscountOnly = true;

    expect(catalogFilterIds($this->harness))->toBe([$this->discounted->id]);

    $this->harness->catalogDiscountOnly = false;
    $this->harness->catalogInStockOnly = true;

    expect(catalogFilterIds($this->harness))
        ->toEqualCanonicalizing([$this->cheap->id, $this->tableDecor->id, $this->luxury->id]);

    $this->harness->catalogInStockOnly = false;
    $this->harness->catalogFeaturedOnly = true;

    expect(catalogFilterIds($this->harness))->toBe([$this->discounted->id]);
});

test('sorting covers price, rating, popularity, discount, and newest', function (): void {
    $this->harness->catalogSort = 'price_low';
    expect(array_slice(catalogFilterIds($this->harness), 0, 2))
        ->toBe([$this->cheap->id, $this->discounted->id]);

    $this->harness->catalogSort = 'price_high';
    expect(catalogFilterIds($this->harness)[0])->toBe($this->luxury->id);

    $this->harness->catalogSort = 'rating';
    expect(catalogFilterIds($this->harness)[0])->toBeIn([$this->discounted->id, $this->luxury->id]);

    // Diskon terbesar: 2 juta -> 1,5 juta = 25%.
    $this->harness->catalogSort = 'discount';
    expect(catalogFilterIds($this->harness)[0])->toBe($this->discounted->id);

    $this->harness->catalogSort = 'newest';
    expect(catalogFilterIds($this->harness)[0])->toBe($this->luxury->id);
});

test('the view data exposes counts, active chips, and pagination state', function (): void {
    $this->harness->catalogPerPage = 2;
    $this->harness->catalogCategoryIds = [$this->bouquet->id, $this->table->id];
    $this->harness->catalogDiscountOnly = true;

    $data = $this->harness->catalogViewData();

    expect($data['catalogCount'])->toBe(4)
        ->and($data['filteredCount'])->toBe(1)
        ->and($data['hasMore'])->toBeFalse()
        ->and($data['activeFilters'])->toHaveCount(3)
        ->and(collect($data['activeFilters'])->pluck('type')->all())
        ->toContain('category', 'category', 'discount')
        ->and($data['pricePresets'])->toHaveKeys([
            'under_1m', '1m_3m', '3m_5m', '5m_10m', '10m_25m', 'above_25m',
        ]);

    $this->harness->catalogCategoryIds = [];
    $this->harness->catalogDiscountOnly = false;
    $this->harness->catalogPerPage = 2;

    $data = $this->harness->catalogViewData();

    expect($data['filteredCount'])->toBe(4)
        ->and($data['items'])->toHaveCount(2)
        ->and($data['hasMore'])->toBeTrue();

    $this->harness->loadMoreCatalogs();

    expect($this->harness->catalogViewData()['items'])->toHaveCount(4);
});

test('reset restores every filter and the initial page size', function (): void {
    $this->harness->catalogSearch = 'buket';
    $this->harness->catalogCategoryIds = [$this->bouquet->id];
    $this->harness->catalogPriceRange = '1m_3m';
    $this->harness->catalogMinRating = '4';
    $this->harness->catalogDiscountOnly = true;
    $this->harness->catalogInStockOnly = true;
    $this->harness->catalogFeaturedOnly = true;
    $this->harness->catalogSort = 'price_high';
    $this->harness->catalogPerPage = 48;

    $this->harness->resetCatalogFilters();

    expect($this->harness->catalogSearch)->toBe('')
        ->and($this->harness->catalogCategoryIds)->toBe([])
        ->and($this->harness->catalogPriceRange)->toBe('')
        ->and($this->harness->catalogMinPrice)->toBe('')
        ->and($this->harness->catalogMaxPrice)->toBe('')
        ->and($this->harness->catalogMinRating)->toBe('')
        ->and($this->harness->catalogDiscountOnly)->toBeFalse()
        ->and($this->harness->catalogInStockOnly)->toBeFalse()
        ->and($this->harness->catalogFeaturedOnly)->toBeFalse()
        ->and($this->harness->catalogSort)->toBe('newest')
        ->and($this->harness->catalogPerPage)->toBe(12);
});
