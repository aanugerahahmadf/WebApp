<?php

namespace App\Filament\Admin\Resources\DiscountResource\Pages\ManageDiscounts;

use App\Filament\Admin\Exports\DiscountExporter\DiscountExporter;
use App\Filament\Admin\Pages\Home\Home;
use App\Filament\Admin\Resources\DiscountResource\DiscountResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageDiscounts extends ManageRecords
{
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
            Home::getUrl() => __('Beranda'),
            DiscountResource::getUrl('index') => DiscountResource::getNavigationLabel(),
        ];
    }
}
