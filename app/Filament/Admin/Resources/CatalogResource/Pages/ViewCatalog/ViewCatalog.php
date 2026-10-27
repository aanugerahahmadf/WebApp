<?php

namespace App\Filament\Admin\Resources\CatalogResource\Pages\ViewCatalog;

use App\Filament\Admin\Resources\CatalogResource\CatalogResource;
use Filament\Resources\Pages\ViewRecord;

class ViewCatalog extends \Filament\Resources\Pages\ViewRecord
{

    protected static string $resource = CatalogResource::class;

    /**
     * Fill the read-only tier fields from the related packages table.
     *
     * Tier fields are virtual form fields (`tier_*`), not columns on
     * `catalogs`, so ViewRecord cannot hydrate them from the catalog model
     * automatically.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return array_merge($data, CatalogResource::tierStateFromRecord($this->record));
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }
}
