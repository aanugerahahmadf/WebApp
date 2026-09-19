<?php

namespace App\Filament\Admin\Resources\OrderResource\Pages\CreateOrder;

use App\Filament\Admin\Resources\OrderResource\OrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
