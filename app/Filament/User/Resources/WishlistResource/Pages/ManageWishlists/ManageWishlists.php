<?php

namespace App\Filament\User\Resources\WishlistResource\Pages\ManageWishlists;

use App\Filament\User\Pages\Home\Home;
use App\Filament\User\Resources\WishlistResource\WishlistResource;
use Filament\Resources\Pages\ManageRecords;

class ManageWishlists extends ManageRecords
{
    protected static string $resource = WishlistResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTitle(): string
    {
        return static::$title ?? static::getResource()::getTitleCasePluralModelLabel();
    }

    public function getBreadcrumbs(): array
    {
        return [
            Home::getUrl() => __('Beranda'),
            WishlistResource::getUrl('index') => WishlistResource::getNavigationLabel(),
        ];
    }
}
