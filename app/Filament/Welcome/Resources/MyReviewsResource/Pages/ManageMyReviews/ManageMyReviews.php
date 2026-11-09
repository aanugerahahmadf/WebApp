<?php

namespace App\Filament\Welcome\Resources\MyReviewsResource\Pages\ManageMyReviews;

use App\Filament\Concerns\HasDynamicBreadcrumbs;
use App\Filament\Welcome\Pages\Home\Home;
use App\Filament\Welcome\Resources\MyReviewsResource\MyReviewsResource;
use Filament\Resources\Pages\ManageRecords;

class ManageMyReviews extends ManageRecords
{
    use HasDynamicBreadcrumbs;

    protected static string $resource = MyReviewsResource::class;

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
            MyReviewsResource::getUrl('index') => MyReviewsResource::getNavigationLabel(),
        ];
    }
}
