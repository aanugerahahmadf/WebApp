<?php

namespace App\Filament\Admin\Resources\DatabaseStudioResource\Pages\ViewDatabaseStudio;

use App\Filament\Admin\Resources\DatabaseStudioResource\DatabaseStudioResource;
use App\Filament\Concerns\HasDynamicBreadcrumbs;
use Filament\Resources\Pages\ViewRecord;

class ViewDatabaseStudio extends \Filament\Resources\Pages\ViewRecord
{
    use HasDynamicBreadcrumbs;

    protected static string $resource = DatabaseStudioResource::class;

    public function getBreadcrumbs(): array
    {
        return [
            ...$this->breadcrumbParentCrumb(),
            DatabaseStudioResource::getUrl('index') => DatabaseStudioResource::getNavigationLabel(),
        ];
    }
}
