<?php

namespace App\Filament\Admin\Resources\OrderResource\Pages\ListOrders;

use App\Filament\Shared\Concerns\HasStandardActions\HasStandardActions;
use App\Filament\Admin\Resources\OrderResource\OrderResource;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends \Filament\Resources\Pages\ListRecords
{
    use HasStandardActions;



    protected static string $resource = OrderResource::class;

        public function getBreadcrumbs(): array
    {
        return [];
    }
}
