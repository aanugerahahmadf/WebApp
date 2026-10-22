<?php

namespace App\Filament\Admin\Resources\WishlistResource\Pages\ManageWishlists;

use App\Filament\Shared\Concerns\HasStandardActions\HasStandardActions;
use App\Filament\Admin\Resources\WishlistResource\WishlistResource;
use App\Filament\Concerns\HasDynamicBreadcrumbs;
use Filament\Resources\Pages\ManageRecords;

class ManageWishlists extends \Filament\Resources\Pages\ManageRecords
{
    use HasStandardActions;

    use HasDynamicBreadcrumbs;


    protected static string $resource = WishlistResource::class;

    public function getBreadcrumbs(): array
    {
        return [
            ...$this->breadcrumbParentCrumb(),
            WishlistResource::getUrl('index') => WishlistResource::getNavigationLabel(),
        ];
    }
}
