<?php

namespace App\Filament\Admin\Resources\OrderResource\Pages\CreateOrder;

use App\Filament\Admin\Pages\Home\Home;
use App\Filament\Admin\Resources\OrderResource\OrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    public function getBreadcrumbs(): array
    {
        return [
            Home::getUrl() => __('Beranda'),
            ...parent::getBreadcrumbs(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
