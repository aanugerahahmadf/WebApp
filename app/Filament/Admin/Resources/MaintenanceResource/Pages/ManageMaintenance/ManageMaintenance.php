<?php

namespace App\Filament\Admin\Resources\MaintenanceResource\Pages\ManageMaintenance;

use App\Filament\Shared\Concerns\HasStandardActions\HasStandardActions;
use App\Filament\Admin\Resources\MaintenanceResource\MaintenanceResource;
use App\Filament\Concerns\HasDynamicBreadcrumbs;
use Filament\Resources\Pages\ManageRecords;

class ManageMaintenance extends \Filament\Resources\Pages\ManageRecords
{
    use HasStandardActions;

    use HasDynamicBreadcrumbs;


    protected static string $resource = MaintenanceResource::class;

    public function getBreadcrumbs(): array
    {
        return [
            ...$this->breadcrumbParentCrumb(),
            MaintenanceResource::getUrl('index') => MaintenanceResource::getNavigationLabel(),
        ];
    }
}
