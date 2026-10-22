<?php

namespace App\Filament\Admin\Resources\CatalogResource\Pages\ViewCatalog;

use App\Filament\Admin\Resources\CatalogResource\CatalogResource;
use App\Filament\Concerns\HasDynamicBreadcrumbs;
use Filament\Resources\Pages\ViewRecord;

class ViewCatalog extends \Filament\Resources\Pages\ViewRecord
{
    use HasDynamicBreadcrumbs;

    protected static string $resource = CatalogResource::class;

    public function getBreadcrumbs(): array
    {
        return [
            ...$this->breadcrumbParentCrumb(),
            CatalogResource::getUrl('index') => CatalogResource::getNavigationLabel(),
        ];
    }
}
