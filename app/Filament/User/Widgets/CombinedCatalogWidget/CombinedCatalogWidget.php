<?php

namespace App\Filament\User\Widgets\CombinedCatalogWidget;

use App\Models\Package\Package;
use App\Models\Product\Product;
use App\Support\AppPlatform\AppPlatform;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

class CombinedCatalogWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected function getTableHeading(): string|Htmlable|null
    {
        return '';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Package::query()->where('is_active', true))
            ->poll(AppPlatform::isNativeMobile() ? null : '30s')
            ->content(fn (): View => view(
                'User.components.combined-catalog-grid.combined-catalog-grid',
                static::catalogViewData()
            ))
            ->paginated(false);
    }

    /**
     * Katalog campuran paket + produk, diselingi satu-satu (paket, produk,
     * paket, produk, ...) supaya grid beranda tidak monoton satu jenis saja.
     * Category + rata-rata rating di-eager-load di sini agar blade murni
     * render tanpa query tambahan (tanpa N+1).
     *
     * @return array{items: Collection<int, Package|Product>, packageCount: int, productCount: int}
     */
    protected static function catalogViewData(): array
    {
        $packages = Package::query()
            ->where('is_active', true)
            ->with(['category'])
            ->withAvg('reviews', 'rating')
            ->latest()
            ->get()
            ->each(fn (Package $package): Package => $package->setAttribute('catalog_type', 'package'));

        $products = Product::query()
            ->where('is_active', true)
            ->with(['category'])
            ->withAvg('reviews', 'rating')
            ->latest()
            ->get()
            ->each(fn (Product $product): Product => $product->setAttribute('catalog_type', 'product'));

        $items = collect();
        $max = max($packages->count(), $products->count());
        for ($index = 0; $index < $max; $index++) {
            if (isset($packages[$index])) {
                $items->push($packages[$index]);
            }
            if (isset($products[$index])) {
                $items->push($products[$index]);
            }
        }

        return [
            'items' => $items,
            'packageCount' => $packages->count(),
            'productCount' => $products->count(),
        ];
    }
}
