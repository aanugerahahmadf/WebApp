<?php

namespace App\Filament\Welcome\Resources\FavoriteResource\Pages\ManageFavorites;

use App\Filament\Concerns\HasDynamicBreadcrumbs;
use App\Filament\Welcome\Pages\Home\Home;
use App\Filament\Welcome\Resources\FavoriteResource\FavoriteResource;
use Filament\Resources\Pages\ManageRecords;

class ManageFavorites extends ManageRecords
{
    use HasDynamicBreadcrumbs;

    protected static string $resource = FavoriteResource::class;

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
            FavoriteResource::getUrl('index') => FavoriteResource::getNavigationLabel(),
        ];
    }
}
