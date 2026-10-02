<?php

namespace App\Filament\Admin\Resources\DiscountResource\Pages\ManageDiscounts;

use App\Filament\Admin\Exports\DiscountExporter\DiscountExporter;
use App\Filament\Admin\Pages\Home\Home;
use App\Filament\Admin\Resources\DiscountResource\DiscountResource;
use App\Filament\Concerns\HasDynamicBreadcrumbs;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageDiscounts extends ManageRecords
{
    use HasDynamicBreadcrumbs;
    protected static string $resource = DiscountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ExportAction::make()
                ->exporter(DiscountExporter::class)
                ->label(__('Ekspor Data'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success'),
            Actions\CreateAction::make()
                ->label(__('Tambah Diskon'))
                ->icon('heroicon-o-plus'),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            ...$this->breadcrumbParentCrumb(),
            DiscountResource::getUrl('index') => DiscountResource::getNavigationLabel(),
        ];
    }
}
