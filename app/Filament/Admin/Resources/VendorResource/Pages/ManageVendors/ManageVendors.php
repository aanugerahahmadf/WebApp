<?php

namespace App\Filament\Admin\Resources\VendorResource\Pages\ManageVendors;

use App\Filament\Admin\Concerns\HasMobilePagination\HasMobilePagination;
use App\Filament\Admin\Exports\VendorExporter\VendorExporter;
use App\Filament\Admin\Pages\Home\Home;
use App\Filament\Admin\Resources\VendorResource\VendorResource;
use App\Filament\Concerns\HasDynamicBreadcrumbs;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

/**
 * @property-read \App\Filament\Admin\Resources\VendorResource\VendorResource $resource
 */
class ManageVendors extends ManageRecords
{
    use HasMobilePagination;
    use HasDynamicBreadcrumbs;
    protected static string $resource = VendorResource::class;

    public function getTitle(): string
    {
        return static::$title ?? static::getResource()::getTitleCasePluralModelLabel();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ExportAction::make()
                ->exporter(VendorExporter::class)
                ->label(__('Ekspor Data'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success'),
            Actions\CreateAction::make()
                ->label(__('Tambah Vendor'))
                ->icon('heroicon-o-plus')
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title(__('Vendor Ditambahkan'))
                        ->body(__('Vendor baru telah berhasil ditambahkan.'))
                ),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            ...$this->breadcrumbParentCrumb(),
            VendorResource::getUrl('index') => VendorResource::getNavigationLabel(),
        ];
    }
}
