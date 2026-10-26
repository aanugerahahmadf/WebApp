<?php

namespace App\Filament\Admin\Resources\CatalogResource\Pages\ViewCatalog;

use App\Filament\Admin\Resources\CatalogResource\CatalogResource;
use Filament\Resources\Pages\ViewRecord;

class ViewCatalog extends \Filament\Resources\Pages\ViewRecord
{

    protected static string $resource = CatalogResource::class;

        public function getBreadcrumbs(): array
    {
        return [];
    }
}
