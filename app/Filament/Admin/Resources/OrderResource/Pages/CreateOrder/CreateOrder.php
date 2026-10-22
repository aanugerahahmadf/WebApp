<?php

namespace App\Filament\Admin\Resources\OrderResource\Pages\CreateOrder;

use App\Filament\Admin\Resources\OrderResource\OrderResource;
use App\Filament\Concerns\HasDynamicBreadcrumbs;
use Filament\Resources\Pages\CreateRecord;

class CreateOrder extends \Filament\Resources\Pages\CreateRecord
{
    use HasDynamicBreadcrumbs;

    protected static string $resource = OrderResource::class;

    public function getBreadcrumbs(): array
    {
        return [
            ...$this->breadcrumbParentCrumb(),
            OrderResource::getUrl('index') => OrderResource::getNavigationLabel(),
        ];
    }
}
