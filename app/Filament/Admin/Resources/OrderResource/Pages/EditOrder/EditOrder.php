<?php

namespace App\Filament\Admin\Resources\OrderResource\Pages\EditOrder;

use App\Filament\Admin\Resources\OrderResource\OrderResource;
use App\Filament\Concerns\HasDynamicBreadcrumbs;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends \Filament\Resources\Pages\EditRecord
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
