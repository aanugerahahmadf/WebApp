<?php

namespace App\Filament\User\Resources\ReviewResource\Pages\ManageReviews;

use App\Filament\Concerns\HasDynamicBreadcrumbs;
use App\Filament\User\Pages\Home\Home;
use App\Filament\User\Resources\ReviewResource\ReviewResource;
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
