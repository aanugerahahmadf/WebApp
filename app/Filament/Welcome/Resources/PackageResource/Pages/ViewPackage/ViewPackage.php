<?php

namespace App\Filament\Welcome\Resources\PackageResource\Pages\ViewPackage;

use App\Filament\Welcome\Pages\Home\Home;
use App\Filament\Welcome\Resources\PackageResource\PackageResource;
use Filament\Resources\Pages\ViewRecord;

class ViewPackage extends ViewRecord
{
    protected static string $resource = PackageResource::class;

    public function getTitle(): string
    {
        return $this->record->name;
    }

    public function getBreadcrumbs(): array
    {
        return [
            Home::getUrl() => __('Beranda'),
            ...parent::getBreadcrumbs(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
