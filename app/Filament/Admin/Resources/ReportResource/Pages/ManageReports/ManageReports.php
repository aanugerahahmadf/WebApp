<?php

namespace App\Filament\Admin\Resources\ReportResource\Pages\ManageReports;

use App\Filament\Admin\Concerns\HasMobilePagination\HasMobilePagination;
use App\Filament\Admin\Pages\Home\Home;
use App\Filament\Admin\Resources\ReportResource\ReportResource;
use App\Filament\Concerns\HasDynamicBreadcrumbs;
use Filament\Resources\Pages\ManageRecords;

class ManageReports extends ManageRecords
{
    use HasMobilePagination;
    use HasDynamicBreadcrumbs;
    protected static string $resource = ReportResource::class;

    public function getTitle(): string
    {
        return static::$title ?? static::getResource()::getTitleCasePluralModelLabel();
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getBreadcrumbs(): array
    {
        return [
            ...$this->breadcrumbParentCrumb(),
            ReportResource::getUrl('index') => ReportResource::getNavigationLabel(),
        ];
    }
}
