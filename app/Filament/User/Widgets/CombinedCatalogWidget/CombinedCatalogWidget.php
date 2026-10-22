<?php

namespace App\Filament\User\Widgets\CombinedCatalogWidget;

use App\Models\Catalog\Catalog;
use App\Support\AppPlatform\AppPlatform;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

class CombinedCatalogWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected function getTableHeading(): string|Htmlable|null
    {
        return '';
    }

    public function table(Table $table): Table
    {
        return $table
            // Query tabel (dipakai Filament untuk sortir dan penghitungan)
            // ikut Catalog, bukan Package. Awalnya Package::query() -- jadi
            // widget bernama "CombinedCatalog" sebenarnya menghitung paket.
            ->query(Catalog::query()->where('is_active', true))
            ->poll(AppPlatform::isNativeMobile() ? null : '30s')
            ->content(fn (): View => view(
                'User.components.combined-catalog-grid.combined-catalog-grid',
                static::catalogViewData()
            ))
            ->paginated(false);
    }

    /**
     * Katalog aktif saja untuk grid beranda.
     *
     * DULU paket dan katalog dicampur selang-seling di sini ("paket, katalog,
     * paket, produk, ..."). Itu tidak sesuai dengan model data sekarang:
     * paket adalah TIER milik katalog, bukan item yang bisa diklik -- begitu
     * diklik, URL-nya tetap menunjuk halaman katalog karena blade selalu memakai
     * `CatalogResource::getUrl()` untuk kedua jenis item. Jadi separuh kartu di
     * beranda sebenarnya kliknya mendarat di katalog yang berbeda.
     *
     * Karena itu paket dihapus dari grid. Kelimanya tetap utuh: tampil sebagai
     * pilihan di halaman detail katalog, dan dikelola lewat
     * CatalogResource::tiers() / TiersRelationManager di panel admin.
     *
     * `category` + rata-rata rating di-eager-load supaya blade murni render
     * tanpa query tambahan (tanpa N+1).
     *
     * @return array{items: Collection<int, Catalog>, packageCount: int, catalogCount: int}
     */
    protected static function catalogViewData(): array
    {
        $catalogs = Catalog::query()
            ->where('is_active', true)
            ->with(['category'])
            ->withAvg('reviews', 'rating')
            ->latest()
            ->get();

        return [
            'items' => $catalogs,
            'catalogCount' => $catalogs->count(),
        ];
    }
}