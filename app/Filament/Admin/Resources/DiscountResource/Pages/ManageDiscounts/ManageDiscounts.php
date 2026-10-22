<?php

namespace App\Filament\Admin\Resources\DiscountResource\Pages\ManageDiscounts;

use App\Filament\Shared\Concerns\HasStandardActions\HasStandardActions;
use App\Filament\Admin\Resources\DiscountResource\DiscountResource;
use App\Filament\Concerns\HasDynamicBreadcrumbs;
use Filament\Resources\Pages\ManageRecords;

class ManageDiscounts extends \Filament\Resources\Pages\ManageRecords
{
    use HasStandardActions;

    use HasDynamicBreadcrumbs;


    protected static string $resource = DiscountResource::class;

    public function getBreadcrumbs(): array
    {
        return [
            ...$this->breadcrumbParentCrumb(),
            DiscountResource::getUrl('index') => DiscountResource::getNavigationLabel(),
        ];
    }
}
