<?php

namespace App\Filament\User\Resources\CartResource\Pages\ManageCarts;

use App\Filament\User\Pages\Home\Home;
use App\Filament\User\Resources\CartResource\CartResource;
use Filament\Resources\Pages\ManageRecords;

class ManageCarts extends ManageRecords
{
    protected static string $resource = CartResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getBreadcrumbs(): array
    {
        return [
            Home::getUrl() => __('Beranda'),
            CartResource::getUrl('index') => CartResource::getNavigationLabel(),
        ];
    }
}
