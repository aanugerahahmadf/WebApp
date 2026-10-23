<?php

namespace App\Filament\Admin\Resources\HeroBannerResource\Pages;

use App\Filament\Admin\Resources\HeroBannerResource\HeroBannerResource;
use App\Filament\Concerns\HasDynamicBreadcrumbs;
use Filament\Resources\Pages\ManageRecords;

class ManageHeroBanners extends ManageRecords
{
    use HasDynamicBreadcrumbs;

    protected static string $resource = HeroBannerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            ...$this->breadcrumbParentCrumb(),
            HeroBannerResource::getUrl('index') => HeroBannerResource::getNavigationLabel(),
        ];
    }
}
