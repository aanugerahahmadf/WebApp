<?php

namespace App\Filament\Admin\Resources\VoucherResource\Pages\ManageVouchers;

use App\Filament\Admin\Exports\VoucherExporter\VoucherExporter;
use App\Filament\Admin\Pages\Home\Home;
use App\Filament\Admin\Resources\VoucherResource\VoucherResource;
use App\Filament\Concerns\HasDynamicBreadcrumbs;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

/**
 * @property-read \App\Filament\Admin\Resources\VoucherResource\VoucherResource $resource
 */
class ManageVouchers extends ManageRecords
{
    use HasDynamicBreadcrumbs;
    protected static string $resource = VoucherResource::class;

    public function getTitle(): string
    {
        return static::$title ?? static::getResource()::getTitleCasePluralModelLabel();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ExportAction::make()
                ->exporter(VoucherExporter::class)
                ->label(__('Ekspor Data'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success'),
            Actions\CreateAction::make()
                ->label(__('Tambah Voucher'))
                ->icon('heroicon-o-plus')
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title(__('Voucher Ditambahkan'))
                        ->body(__('Voucher baru telah berhasil ditambahkan.'))
                ),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            ...$this->breadcrumbParentCrumb(),
            VoucherResource::getUrl('index') => VoucherResource::getNavigationLabel(),
        ];
    }
}
