<?php

namespace App\Filament\Welcome\Resources\WishlistResource\Pages\ManageWishlists;

use App\Filament\Concerns\HasDynamicBreadcrumbs;
use App\Filament\Welcome\Pages\Home\Home;
use App\Filament\Welcome\Resources\WishlistResource\WishlistResource;
use Filament\Resources\Pages\ManageRecords;

class ManageWishlists extends ManageRecords
{
    use HasDynamicBreadcrumbs;

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
            ...$this->breadcrumbParentCrumb(),
            WishlistResource::getUrl('index') => WishlistResource::getNavigationLabel(),
        ];
    }
}
