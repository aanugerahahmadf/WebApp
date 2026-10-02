<?php

namespace App\Filament\Admin\Resources\TermsOfServiceResource\Pages\ManageTermsOfServices;

use App\Filament\Admin\Exports\TermsOfServiceExporter\TermsOfServiceExporter;
use App\Filament\Admin\Pages\Home\Home;
use App\Filament\Admin\Resources\TermsOfServiceResource\TermsOfServiceResource;
use App\Filament\Concerns\HasDynamicBreadcrumbs;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageTermsOfServices extends ManageRecords
{
    use HasDynamicBreadcrumbs;
    protected static string $resource = TermsOfServiceResource::class;

    public function getTitle(): string
    {
        return static::$title ?? static::getResource()::getTitleCasePluralModelLabel();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ExportAction::make()
                ->exporter(TermsOfServiceExporter::class)
                ->label(__('Ekspor Data'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success'),
            Actions\CreateAction::make()
                ->label(__('Tambah Ketentuan Layanan'))
                ->icon('heroicon-o-plus')
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title(__('Ketentuan Layanan Ditambahkan'))
                        ->body(__('Ketentuan layanan baru telah berhasil ditambahkan.'))
                ),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            ...$this->breadcrumbParentCrumb(),
            TermsOfServiceResource::getUrl('index') => TermsOfServiceResource::getNavigationLabel(),
        ];
    }
}
