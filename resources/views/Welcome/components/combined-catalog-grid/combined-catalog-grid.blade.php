@include('Shared.components.combined-catalog.combined-catalog', [
    'catalogResource' => $catalogResource ?? \App\Filament\Welcome\Resources\CatalogResource\CatalogResource::class,
])
