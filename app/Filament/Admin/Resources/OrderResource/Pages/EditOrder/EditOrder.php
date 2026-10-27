<?php

namespace App\Filament\Admin\Resources\OrderResource\Pages\EditOrder;

use App\Filament\Admin\Resources\OrderResource\OrderResource;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends \Filament\Resources\Pages\EditRecord
{

    protected static string $resource = OrderResource::class;

        public function getBreadcrumbs(): array
    {
        return [];
    }
}
