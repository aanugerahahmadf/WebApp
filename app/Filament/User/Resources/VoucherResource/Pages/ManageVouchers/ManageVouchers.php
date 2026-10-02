<?php

namespace App\Filament\User\Resources\VoucherResource\Pages\ManageVouchers;

use App\Filament\Concerns\HasDynamicBreadcrumbs;
use App\Filament\User\Pages\Home\Home;
use App\Filament\User\Resources\VoucherResource\VoucherResource;
use Filament\Resources\Pages\ManageRecords;

class ManageVouchers extends ManageRecords
{
    use HasDynamicBreadcrumbs;

    protected static string $resource = VoucherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No creation action for user
        ];
    }

    public function getTitle(): string
    {
        return static::$title ?? static::getResource()::getTitleCasePluralModelLabel();
    }

    public function getBreadcrumbs(): array
    {
        return [
            ...$this->breadcrumbParentCrumb(),
            VoucherResource::getUrl('index') => VoucherResource::getNavigationLabel(),
        ];
    }
}
