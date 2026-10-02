<?php

namespace App\Filament\Welcome\Resources\ReviewResource\Pages\ManageReviews;

use App\Filament\Concerns\HasDynamicBreadcrumbs;
use App\Filament\Welcome\Pages\Home\Home;
use App\Filament\Welcome\Resources\ReviewResource\ReviewResource;
use Filament\Resources\Pages\ManageRecords;

class ManageReviews extends ManageRecords
{
    use HasDynamicBreadcrumbs;

    protected static string $resource = ReviewResource::class;

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
            ReviewResource::getUrl('index') => ReviewResource::getNavigationLabel(),
        ];
    }
}
