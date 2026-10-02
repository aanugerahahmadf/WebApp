<?php

namespace App\Filament\User\Resources\CartResource\Pages\ManageCarts;

use App\Filament\Concerns\HasDynamicBreadcrumbs;
use App\Filament\User\Pages\Home\Home;
use App\Filament\User\Resources\CartResource\CartResource;
use Filament\Resources\Pages\ManageRecords;

class ManageCarts extends ManageRecords
{
    use HasDynamicBreadcrumbs;

    protected static string $resource = CartResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getBreadcrumbs(): array
    {
        return [
            ...$this->breadcrumbParentCrumb(),
            CartResource::getUrl('index') => CartResource::getNavigationLabel(),
        ];
    }
}
