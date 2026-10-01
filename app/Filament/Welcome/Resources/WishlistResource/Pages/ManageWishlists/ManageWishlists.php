<?php

namespace App\Filament\Welcome\Resources\WishlistResource\Pages\ManageWishlists;

use App\Filament\Welcome\Resources\WishlistResource\WishlistResource;
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
}
