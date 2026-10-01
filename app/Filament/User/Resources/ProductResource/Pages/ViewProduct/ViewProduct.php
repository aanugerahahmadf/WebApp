<?php

namespace App\Filament\User\Resources\ProductResource\Pages\ViewProduct;

use App\Filament\User\Pages\Home\Home;
use App\Filament\User\Resources\ProductResource\ProductResource;
use Filament\Resources\Pages\ViewRecord;

class ViewProduct extends ViewRecord
{
    protected static string $resource = ProductResource::class;

    public function getTitle(): string
    {
        return $this->record->name;
    }

    public function getBreadcrumbs(): array
    {
        return [
            Home::getUrl() => __('Beranda'),
            ...parent::getBreadcrumbs(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
