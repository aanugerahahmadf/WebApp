<?php

namespace App\Filament\User\Resources\MyReviewsResource\Pages\ManageMyReviews;

use App\Filament\Concerns\HasDynamicBreadcrumbs;
use App\Filament\User\Resources\MyReviewsResource\MyReviewsResource;
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
