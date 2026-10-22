<?php

namespace App\Filament\Admin\Resources\OrderResource\Pages\ViewOrder;

use App\Filament\Admin\Resources\OrderResource\OrderResource;
use App\Filament\Concerns\HasDynamicBreadcrumbs;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends \Filament\Resources\Pages\ViewRecord
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
