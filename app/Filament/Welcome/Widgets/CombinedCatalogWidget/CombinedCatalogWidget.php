<?php

namespace App\Filament\Welcome\Widgets\CombinedCatalogWidget;

use App\Filament\Shared\Concerns\HasCatalogFilters;
use App\Filament\Welcome\Resources\CatalogResource\CatalogResource;
use App\Models\Catalog\Catalog;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;

class CombinedCatalogWidget extends BaseWidget
{
    use HasCatalogFilters;

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
            ->poll('30s')
            ->content(fn (): View => view(
                'Welcome.components.combined-catalog-grid.combined-catalog-grid',
                $this->catalogViewData()
            ))
            ->paginated(false);
    }

    /**
     * URL kartu memakai resource panel Welcome.
     */
    protected static function catalogResourceClass(): string
    {
        return CatalogResource::class;
    }
}
