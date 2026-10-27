<?php

namespace App\Filament\Shared\Concerns;

use App\Models\Catalog\Catalog;
use Illuminate\Support\Collection;

/**
 * Filter katalog lengkap untuk widget CombinedCatalog di panel User & Welcome.
 *
 * Kenapa filter ini hidup di widget (bukan di Table Filament):
 * widget ini merender grid sendiri lewat `Table::content()`, jadi filter bawaan
 * tabel tidak pernah ikut menyaring apa pun. Semua state filter di bawah adalah
 * public property Livewire yang di-bind langsung dari blade.
 *
 * Semua penyaringan dilakukan di PHP setelah satu query mengambil katalog aktif
 * (saat ini ~100 baris). Itu sengaja: grid butuh `final_price` (harga diskon yang
 * dihitung di accessor), rata-rata rating, dan hitungan pesanan sekaligus, dan
 * memindahkannya ke SQL akan menduplikasi logika accessor model.
 */
trait HasCatalogFilters
{
    public string $catalogSearch = '';

    /** @var array<int, int|string> */
    public array $catalogCategoryIds = [];

    public string $catalogPriceRange = '';

    public string $catalogMinPrice = '';

    public string $catalogMaxPrice = '';

    public string $catalogMinRating = '';

    public bool $catalogDiscountOnly = false;

    public bool $catalogInStockOnly = false;

    public bool $catalogFeaturedOnly = false;

    public string $catalogSort = 'newest';

    public int $catalogPerPage = 12;

    public bool $catalogFiltersOpen = true;

    /**
     * Class CatalogResource milik panel masing-masing widget.
     *
     * Wajib diisi kelas pemakai supaya URL kartu tetap memakai prefix panel
     * yang benar (`/flowerdecorationscatalog/...`, bukan `/user/...`).
     */
    abstract protected static function catalogResourceClass(): string;

    public function updatedCatalogSearch(): void
    {
        $this->resetCatalogPage();
    }

    public function updatedCatalogCategoryIds(): void
    {
        $this->resetCatalogPage();
    }

    public function updatedCatalogPriceRange(): void
    {
        if ($this->catalogPriceRange !== '') {
            $this->catalogMinPrice = '';
            $this->catalogMaxPrice = '';
        }

        $this->resetCatalogPage();
    }

    public function updatedCatalogMinPrice(): void
    {
        if ($this->catalogMinPrice !== '') {
            $this->catalogPriceRange = '';
        }

        $this->resetCatalogPage();
    }

    public function updatedCatalogMaxPrice(): void
    {
        if ($this->catalogMaxPrice !== '') {
            $this->catalogPriceRange = '';
        }

        $this->resetCatalogPage();
    }

    public function updatedCatalogMinRating(): void
    {
        $this->resetCatalogPage();
    }

    public function updatedCatalogDiscountOnly(): void
    {
        $this->resetCatalogPage();
    }

    public function updatedCatalogInStockOnly(): void
    {
        $this->resetCatalogPage();
    }

    public function updatedCatalogFeaturedOnly(): void
    {
        $this->resetCatalogPage();
    }

    /**
     * Urutan dipilih dari dropdown kustom (bukan <select>), karena popup
     * <select> bawaan browser digambar OS dan sudutnya selalu lancip.
     */
    public function setCatalogSort(string $sort): void
    {
        if (! array_key_exists($sort, $this->catalogSortOptions())) {
            return;
        }

        $this->catalogSort = $sort;
        $this->resetCatalogPage();
    }

    /**
     * Pilihan rating minimum dalam bintang (bukan angka).
     *
     * @return array<string, float>
     */
    protected function catalogRatingOptions(): array
    {
        return [
            '1' => 1.0,
            '2' => 2.0,
            '3' => 3.0,
            '4' => 4.0,
            '4.5' => 4.5,
            '5' => 5.0,
        ];
    }

    public function toggleCatalogFilters(): void
    {
        $this->catalogFiltersOpen = ! $this->catalogFiltersOpen;
    }

    public function clearCatalogCategories(): void
    {
        $this->catalogCategoryIds = [];
        $this->resetCatalogPage();
    }

    public function resetCatalogFilters(): void
    {
        $this->catalogSearch = '';
        $this->catalogCategoryIds = [];
        $this->catalogPriceRange = '';
        $this->catalogMinPrice = '';
        $this->catalogMaxPrice = '';
        $this->catalogMinRating = '';
        $this->catalogDiscountOnly = false;
        $this->catalogInStockOnly = false;
        $this->catalogFeaturedOnly = false;
        $this->catalogSort = 'newest';
        $this->resetCatalogPage();
    }

    public function loadMoreCatalogs(): void
    {
        $this->catalogPerPage += static::catalogInitialPerPage();
    }

    /**
     * Hapus satu filter aktif dari chip.
     *
     * Chip kategori butuh nilai id, karena satu chip mewakili satu kategori.
     */
    public function removeCatalogFilter(string $type, ?string $value = null): void
    {
        match ($type) {
            'search' => $this->catalogSearch = '',
            'category' => $this->catalogCategoryIds = array_values(array_filter(
                $this->catalogCategoryIds,
                fn ($id): bool => (string) $id !== (string) $value,
            )),
            'price' => $this->catalogPriceRange = '',
            'price_min' => $this->catalogMinPrice = '',
            'price_max' => $this->catalogMaxPrice = '',
            'rating' => $this->catalogMinRating = '',
            'discount' => $this->catalogDiscountOnly = false,
            'stock' => $this->catalogInStockOnly = false,
            'featured' => $this->catalogFeaturedOnly = false,
            default => null,
        };

        $this->resetCatalogPage();
    }

    /**
     * Data lengkap untuk blade.
     *
     * @return array<string, mixed>
     */
    public function catalogViewData(): array
    {
        $catalogs = Catalog::query()
            ->where('is_active', true)
            ->with(['category', 'media'])
            ->withAvg('reviews', 'rating')
            ->withCount(['reviews', 'orders'])
            ->get();

        $categories = $catalogs
            ->pluck('category')
            ->filter()
            ->unique('id')
            ->sortBy(fn ($category): string => (string) __($category->name))
            ->values();

        $categoryCounts = $catalogs->groupBy('category_id')->map->count();

        // 50 dari 100 katalog di data saat ini memang tidak punya kategori
        // (`category_id` NULL). Tanpa chip sintetis ini, katalog tersebut tidak
        // bisa dijangkau lewat filter kategori sama sekali -- padahal justru
        // separuh katalog.
        $uncategorizedCount = (int) ($categoryCounts->get('') ?? 0);

        if ($uncategorizedCount > 0) {
            $categories->push((object) ['id' => 0, 'name' => __('Tanpa kategori')]);
            $categoryCounts[0] = $uncategorizedCount;
        }

        $pricePresets = $this->catalogPricePresets($catalogs);

        $filtered = $this->sortCatalogItems($this->applyCatalogFilters($catalogs));

        $filteredCount = $filtered->count();
        $visible = $filtered->take($this->catalogPerPage)->values();

        return [
            'items' => $visible,
            'catalogCount' => $catalogs->count(),
            'filteredCount' => $filteredCount,
            'categories' => $categories,
            'categoryCounts' => $categoryCounts,
            'pricePresets' => $pricePresets,
            'activeFilters' => $this->activeCatalogFilters($categories, $pricePresets),
            'hasMore' => $filteredCount > $visible->count(),
            'catalogResource' => static::catalogResourceClass(),
            'sortOptions' => $this->catalogSortOptions(),
            'ratingOptions' => $this->catalogRatingOptions(),
            // Nilai state filter ikut dikirim eksplisit: view grid dirender dari
            // closure `Table::content()`, jadi public property Livewire tidak
            // otomatis tersedia di dalamnya.
            'catalogSearch' => $this->catalogSearch,
            'catalogCategoryIds' => array_map('intval', $this->catalogCategoryIds),
            'catalogPriceRange' => $this->catalogPriceRange,
            'catalogMinPrice' => $this->catalogMinPrice,
            'catalogMaxPrice' => $this->catalogMaxPrice,
            'catalogMinRating' => $this->catalogMinRating,
            'catalogDiscountOnly' => $this->catalogDiscountOnly,
            'catalogInStockOnly' => $this->catalogInStockOnly,
            'catalogFeaturedOnly' => $this->catalogFeaturedOnly,
            'catalogSort' => $this->catalogSort,
            'catalogFiltersOpen' => $this->catalogFiltersOpen,
        ];
    }

    /**
     * @param  Collection<int, Catalog>  $catalogs
     * @return Collection<int, Catalog>
     */
    protected function applyCatalogFilters(Collection $catalogs): Collection
    {
        $search = mb_strtolower(trim($this->catalogSearch));
        $categoryIds = array_map('intval', $this->catalogCategoryIds);
        $pricePresets = $this->catalogPricePresets($catalogs);
        $pricePreset = $pricePresets[$this->catalogPriceRange] ?? null;
        $customMinPrice = $this->catalogMinPrice !== '' && (float) $this->catalogMinPrice > 0
            ? (float) $this->catalogMinPrice
            : null;
        $customMaxPrice = $this->catalogMaxPrice !== '' && (float) $this->catalogMaxPrice > 0
            ? (float) $this->catalogMaxPrice
            : null;
        $minRating = $this->catalogMinRating !== '' ? (float) $this->catalogMinRating : null;

        return $catalogs->filter(function (Catalog $catalog) use ($search, $categoryIds, $pricePreset, $customMinPrice, $customMaxPrice, $minRating): bool {
            if ($search !== '') {
                $features = is_array($catalog->features) ? implode(' ', $catalog->features) : '';
                $haystack = mb_strtolower(implode(' ', array_filter([
                    (string) $catalog->name,
                    (string) $catalog->description,
                    (string) $catalog->category?->name,
                    $features,
                ])));

                if (! str_contains($haystack, $search)) {
                    return false;
                }
            }

            if ($categoryIds !== [] && ! in_array((int) $catalog->category_id, $categoryIds, true)) {
                return false;
            }

            if ($pricePreset !== null) {
                $price = (float) $catalog->final_price;

                if ($pricePreset['min'] !== null && $price < $pricePreset['min']) {
                    return false;
                }

                if ($pricePreset['max'] !== null && $price >= $pricePreset['max']) {
                    return false;
                }
            }

            if ($customMinPrice !== null || $customMaxPrice !== null) {
                $price = (float) $catalog->final_price;

                if ($customMinPrice !== null && $price < $customMinPrice) {
                    return false;
                }

                if ($customMaxPrice !== null && $price > $customMaxPrice) {
                    return false;
                }
            }

            if ($minRating !== null && (float) ($catalog->reviews_avg_rating ?? 0) < $minRating) {
                return false;
            }

            if ($this->catalogDiscountOnly && (float) $catalog->discount_price <= 0) {
                return false;
            }

            if ($this->catalogInStockOnly && (int) $catalog->stock <= 0) {
                return false;
            }

            if ($this->catalogFeaturedOnly && ! $catalog->is_featured) {
                return false;
            }

            return true;
        });
    }

    /**
     * @param  Collection<int, Catalog>  $catalogs
     * @return Collection<int, Catalog>
     */
    protected function sortCatalogItems(Collection $catalogs): Collection
    {
        return match ($this->catalogSort) {
            'popular' => $catalogs->sortByDesc('orders_count')->values(),
            'rating' => $catalogs->sortByDesc('orders_count')
                ->sortByDesc('reviews_avg_rating')
                ->values(),
            'price_low' => $catalogs->sortBy(fn (Catalog $catalog): float => (float) $catalog->final_price)->values(),
            'price_high' => $catalogs->sortByDesc(fn (Catalog $catalog): float => (float) $catalog->final_price)->values(),
            'discount' => $catalogs->sortByDesc(fn (Catalog $catalog): float => $this->catalogDiscountPercent($catalog))->values(),
            'name_asc' => $catalogs->sortBy(fn (Catalog $catalog): string => mb_strtolower((string) $catalog->name))->values(),
            'oldest' => $catalogs->sortBy('created_at')->values(),
            default => $catalogs->sortByDesc('created_at')->values(),
        };
    }

    /**
     * Preset harga memakai `final_price` (harga setelah diskon) supaya hasil
     * filter sama dengan angka yang tampil di kartu.
     *
     * @param  Collection<int, Catalog>  $catalogs
     * @return array<string, array{label: string, min: int|null, max: int|null, count: int}>
     */
    protected function catalogPricePresets(Collection $catalogs): array
    {
        $presets = [
            'under_1m' => ['label' => __('Di bawah Rp1 juta'), 'min' => null, 'max' => 1_000_000],
            '1m_3m' => ['label' => __('Rp1 juta – Rp3 juta'), 'min' => 1_000_000, 'max' => 3_000_000],
            '3m_5m' => ['label' => __('Rp3 juta – Rp5 juta'), 'min' => 3_000_000, 'max' => 5_000_000],
            '5m_10m' => ['label' => __('Rp5 juta – Rp10 juta'), 'min' => 5_000_000, 'max' => 10_000_000],
            '10m_25m' => ['label' => __('Rp10 juta – Rp25 juta'), 'min' => 10_000_000, 'max' => 25_000_000],
            'above_25m' => ['label' => __('Di atas Rp25 juta'), 'min' => 25_000_000, 'max' => null],
        ];

        foreach ($presets as $key => $preset) {
            $presets[$key]['count'] = $catalogs->filter(function (Catalog $catalog) use ($preset): bool {
                $price = (float) $catalog->final_price;

                if ($preset['min'] !== null && $price < $preset['min']) {
                    return false;
                }

                if ($preset['max'] !== null && $price >= $preset['max']) {
                    return false;
                }

                return true;
            })->count();
        }

        return $presets;
    }

    /**
     * @return array<string, string>
     */
    protected function catalogSortOptions(): array
    {
        return [
            'newest' => __('Terbaru'),
            'popular' => __('Paling populer'),
            'rating' => __('Rating tertinggi'),
            'price_low' => __('Harga terendah'),
            'price_high' => __('Harga tertinggi'),
            'discount' => __('Diskon terbesar'),
            'name_asc' => __('Nama A–Z'),
            'oldest' => __('Terlama'),
        ];
    }

    /**
     * Chip filter aktif. `value` hanya dipakai tipe kategori; `stars` hanya
     * dipakai tipe rating (supaya chip menampilkan bintang, bukan angka).
     *
     * @param  Collection<int, mixed>  $categories
     * @param  array<string, array{label: string, min: int|null, max: int|null, count: int}>  $pricePresets
     * @return array<int, array{type: string, label: string, value?: string, stars?: float}>
     */
    protected function activeCatalogFilters(Collection $categories, array $pricePresets): array
    {
        $chips = [];

        if (trim($this->catalogSearch) !== '') {
            $chips[] = [
                'type' => 'search',
                'label' => __('Cari: :term', ['term' => '"'.trim($this->catalogSearch).'"']),
            ];
        }

        $selectedCategories = array_map('intval', $this->catalogCategoryIds);

        foreach ($categories as $category) {
            if (in_array((int) $category->id, $selectedCategories, true)) {
                $chips[] = [
                    'type' => 'category',
                    'value' => (string) $category->id,
                    'label' => __($category->name),
                ];
            }
        }

        if ($this->catalogPriceRange !== '' && isset($pricePresets[$this->catalogPriceRange])) {
            $chips[] = [
                'type' => 'price',
                'label' => $pricePresets[$this->catalogPriceRange]['label'],
            ];
        }

        if ($this->catalogMinPrice !== '' && (float) $this->catalogMinPrice > 0) {
            $chips[] = [
                'type' => 'price_min',
                'label' => __('Harga min. Rp:price', ['price' => number_format((float) $this->catalogMinPrice, 0, ',', '.')]),
            ];
        }

        if ($this->catalogMaxPrice !== '' && (float) $this->catalogMaxPrice > 0) {
            $chips[] = [
                'type' => 'price_max',
                'label' => __('Harga maks. Rp:price', ['price' => number_format((float) $this->catalogMaxPrice, 0, ',', '.')]),
            ];
        }

        if ($this->catalogMinRating !== '') {
            $chips[] = [
                'type' => 'rating',
                'label' => __('Rating minimum'),
                // Angka threshold tidak ditampilkan; blade merendernya sebagai
                // bintang supaya chip aktif konsisten dengan pilihan filternya.
                'stars' => (float) $this->catalogMinRating,
            ];
        }

        if ($this->catalogDiscountOnly) {
            $chips[] = ['type' => 'discount', 'label' => __('Sedang diskon')];
        }

        if ($this->catalogInStockOnly) {
            $chips[] = ['type' => 'stock', 'label' => __('Stok tersedia')];
        }

        if ($this->catalogFeaturedOnly) {
            $chips[] = ['type' => 'featured', 'label' => __('Katalog unggulan')];
        }

        return $chips;
    }

    protected function catalogDiscountPercent(Catalog $catalog): float
    {
        $price = (float) $catalog->price;
        $discountPrice = (float) $catalog->discount_price;

        if ($price <= 0 || $discountPrice <= 0) {
            return 0.0;
        }

        return (($price - $discountPrice) / $price) * 100;
    }

    protected function resetCatalogPage(): void
    {
        $this->catalogPerPage = static::catalogInitialPerPage();
    }

    protected static function catalogInitialPerPage(): int
    {
        return 12;
    }
}
