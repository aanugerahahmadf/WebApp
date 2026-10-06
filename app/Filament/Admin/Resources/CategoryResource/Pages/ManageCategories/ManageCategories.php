<?php

namespace App\Filament\Admin\Resources\CategoryResource\Pages\ManageCategories;

use App\Filament\Admin\Concerns\HasMobilePagination\HasMobilePagination;
use App\Filament\Admin\Exports\CategoryExporter\CategoryExporter;
use App\Filament\Admin\Pages\Home\Home;
use App\Filament\Admin\Resources\CategoryResource\CategoryResource;
use App\Filament\Concerns\HasDynamicBreadcrumbs;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

/**
 * @property-read \App\Filament\Admin\Resources\CategoryResource\CategoryResource $resource
 */
class ManageCategories extends ManageRecords
{
    use HasMobilePagination;
    use HasDynamicBreadcrumbs;
    protected static string $resource = CategoryResource::class;

    public function getTitle(): string
    {
        return static::$title ?? static::getResource()::getTitleCasePluralModelLabel();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ExportAction::make()
                ->exporter(CategoryExporter::class)
                ->label(__('Ekspor Data'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success'),
            Actions\CreateAction::make()
                ->label(__('Tambah Kategori'))
                ->icon('heroicon-o-plus')
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title(__('Kategori Ditambahkan'))
                        ->body(__('Kategori baru telah berhasil ditambahkan.'))
                ),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            ...$this->breadcrumbParentCrumb(),
            CategoryResource::getUrl('index') => CategoryResource::getNavigationLabel(),
        ];
    }
}
