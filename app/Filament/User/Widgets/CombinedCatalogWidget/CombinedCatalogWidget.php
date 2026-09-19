<?php

namespace App\Filament\User\Widgets\CombinedCatalogWidget;

use App\Models\Package\Package;
use App\Providers\NativeServiceProvider\NativeServiceProvider;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Contracts\Support\Htmlable;

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
            ->query(Package::query())
            ->poll(NativeServiceProvider::isNativeMobile() ? null : '30s')
            ->content(view('User.components.combined-catalog-grid.combined-catalog-grid'))
            ->paginated(false);
    }
}
