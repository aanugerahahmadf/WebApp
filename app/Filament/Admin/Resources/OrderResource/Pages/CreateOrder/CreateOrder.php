<?php

namespace App\Filament\Admin\Resources\OrderResource\Pages\CreateOrder;

use App\Filament\Admin\Resources\OrderResource\OrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOrder extends \Filament\Resources\Pages\CreateRecord
{

    protected static string $resource = OrderResource::class;

        public function getBreadcrumbs(): array
    {
        return [];
    }
}
