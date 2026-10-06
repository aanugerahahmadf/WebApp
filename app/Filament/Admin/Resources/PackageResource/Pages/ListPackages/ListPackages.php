<?php

namespace App\Filament\Admin\Resources\PackageResource\Pages\ListPackages;

use App\Filament\Admin\Concerns\HasMobilePagination\HasMobilePagination;
use App\Filament\Admin\Exports\PackageExporter\PackageExporter;
use App\Filament\Admin\Pages\Home\Home;
use App\Filament\Admin\Resources\PackageResource\PackageResource;
use App\Filament\Concerns\HasDynamicBreadcrumbs;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListPackages extends ListRecords
{
    use HasMobilePagination;
    use HasDynamicBreadcrumbs;
    protected static string $resource = PackageResource::class;

    public function getTitle(): string
    {
        return static::$title ?? static::getResource()::getTitleCasePluralModelLabel();
    }

    public function getBreadcrumbs(): array
    {
        return [
            ...$this->breadcrumbParentCrumb(),
            PackageResource::getUrl('index') => PackageResource::getNavigationLabel(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ExportAction::make()
                ->exporter(PackageExporter::class)
                ->label(__('Ekspor Data'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success'),
            Actions\CreateAction::make()
                ->label(__('Tambah Paket'))
                ->icon('heroicon-o-plus')
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title(__('Paket Ditambahkan'))
                        ->body(__('Paket baru telah berhasil ditambahkan.'))
                ),
        ];
    }
}
