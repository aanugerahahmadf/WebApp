<?php

namespace App\Filament\Admin\Resources\VoucherResource\Pages\ManageVouchers;

use App\Filament\Shared\Concerns\HasStandardActions\HasStandardActions;
use App\Filament\Admin\Resources\VoucherResource\VoucherResource;
use App\Filament\Concerns\HasDynamicBreadcrumbs;
use Filament\Resources\Pages\ManageRecords;

class ManageVouchers extends \Filament\Resources\Pages\ManageRecords
{
    use HasStandardActions;

    use HasDynamicBreadcrumbs;


    protected static string $resource = VoucherResource::class;

    public function getBreadcrumbs(): array
    {
        return [
            ...$this->breadcrumbParentCrumb(),
            VoucherResource::getUrl('index') => VoucherResource::getNavigationLabel(),
        ];
    }
}
