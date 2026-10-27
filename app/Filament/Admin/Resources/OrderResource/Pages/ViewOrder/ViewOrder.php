<?php

namespace App\Filament\Admin\Resources\OrderResource\Pages\ViewOrder;

use App\Filament\Admin\Resources\OrderResource\OrderResource;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends \Filament\Resources\Pages\ViewRecord
{

    protected static string $resource = OrderResource::class;

        public function getBreadcrumbs(): array
    {
        return [];
    }
}
