<?php

namespace App\Filament\Admin\Resources\ReferenceOptionResource\Pages\ManageReferenceOptions;

use App\Filament\Shared\Concerns\HasStandardActions\HasStandardActions;
use App\Filament\Admin\Resources\ReferenceOptionResource\ReferenceOptionResource;
use App\Filament\Concerns\HasDynamicBreadcrumbs;
use Filament\Resources\Pages\ManageRecords;

class ManageReferenceOptions extends \Filament\Resources\Pages\ManageRecords
{
    use HasStandardActions;

    use HasDynamicBreadcrumbs;


    protected static string $resource = ReferenceOptionResource::class;

    public function getBreadcrumbs(): array
    {
        return [
            ...$this->breadcrumbParentCrumb(),
            ReferenceOptionResource::getUrl('index') => ReferenceOptionResource::getNavigationLabel(),
        ];
    }
}
