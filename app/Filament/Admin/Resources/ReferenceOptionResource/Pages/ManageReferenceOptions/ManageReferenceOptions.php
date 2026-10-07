<?php

namespace App\Filament\Admin\Resources\ReferenceOptionResource\Pages\ManageReferenceOptions;

use App\Filament\Admin\Concerns\HasMobilePagination\HasMobilePagination;
use App\Filament\Admin\Exports\ReferenceOptionExporter\ReferenceOptionExporter;
use App\Filament\Admin\Pages\Home\Home;
use App\Filament\Admin\Resources\ReferenceOptionResource\ReferenceOptionResource;
use App\Filament\Concerns\HasDynamicBreadcrumbs;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageReferenceOptions extends ManageRecords
{
    use HasMobilePagination;
    use HasDynamicBreadcrumbs;
    protected static string $resource = ReferenceOptionResource::class;

    public function getTitle(): string
    {
        return static::$title ?? static::getResource()::getTitleCasePluralModelLabel();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ExportAction::make()
                ->exporter(ReferenceOptionExporter::class)
                ->label(__('Ekspor Data'))
                ->icon('heroicon-s-arrow-down-tray')
                ->color('success'),
            Actions\CreateAction::make()
                ->label(__('Tambah Opsi'))
                ->icon('heroicon-s-plus')
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title(__('Opsi Ditambahkan'))
                        ->body(__('Opsi referensi baru telah berhasil ditambahkan.'))
                ),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            ...$this->breadcrumbParentCrumb(),
            ReferenceOptionResource::getUrl('index') => ReferenceOptionResource::getNavigationLabel(),
        ];
    }
}
