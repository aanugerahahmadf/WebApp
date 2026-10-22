<?php

namespace App\Filament\Admin\Resources\ReviewResource\Pages\ManageReviews;

use App\Filament\Shared\Concerns\HasStandardActions\HasStandardActions;
use App\Filament\Admin\Resources\ReviewResource\ReviewResource;
use App\Filament\Concerns\HasDynamicBreadcrumbs;
use Filament\Resources\Pages\ManageRecords;

class ManageReviews extends \Filament\Resources\Pages\ManageRecords
{
    use HasStandardActions;

    use HasDynamicBreadcrumbs;


    protected static string $resource = ReviewResource::class;

    public function getBreadcrumbs(): array
    {
        return [
            ...$this->breadcrumbParentCrumb(),
            ReviewResource::getUrl('index') => ReviewResource::getNavigationLabel(),
        ];
    }
}
