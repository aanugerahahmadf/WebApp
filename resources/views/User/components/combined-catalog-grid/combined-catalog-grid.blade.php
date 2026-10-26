@include('Shared.components.combined-catalog.combined-catalog', [
    'catalogResource' => $catalogResource ?? \App\Filament\User\Resources\CatalogResource\CatalogResource::class,
])
