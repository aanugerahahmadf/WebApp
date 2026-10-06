<?php

namespace App\Filament\Admin\Resources\HelpResource\Pages\ManageHelps;

use App\Filament\Admin\Concerns\HasMobilePagination\HasMobilePagination;
use App\Filament\Admin\Exports\HelpExporter\HelpExporter;
use App\Filament\Admin\Pages\Home\Home;
use App\Filament\Admin\Resources\HelpResource\HelpResource;
use App\Filament\Concerns\HasDynamicBreadcrumbs;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageHelps extends ManageRecords
{
    use HasMobilePagination;
    use HasDynamicBreadcrumbs;
    protected static string $resource = HelpResource::class;

    public function getTitle(): string
    {
        return static::$title ?? static::getResource()::getTitleCasePluralModelLabel();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ExportAction::make()
                ->exporter(HelpExporter::class)
                ->label(__('Ekspor Data'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success'),
            Actions\CreateAction::make()
                ->label(__('Tambah Pusat Bantuan'))
                ->icon('heroicon-o-plus')
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title(__('Pusat Bantuan Ditambahkan'))
                        ->body(__('Pusat bantuan baru telah berhasil ditambahkan.'))
                ),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            ...$this->breadcrumbParentCrumb(),
            HelpResource::getUrl('index') => HelpResource::getNavigationLabel(),
        ];
    }
}
