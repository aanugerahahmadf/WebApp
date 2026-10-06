<?php

namespace App\Filament\Admin\Resources\ReviewResource\Pages\ManageReviews;

use App\Filament\Admin\Concerns\HasMobilePagination\HasMobilePagination;
use App\Filament\Admin\Exports\ReviewExporter\ReviewExporter;
use App\Filament\Admin\Pages\Home\Home;
use App\Filament\Admin\Resources\ReviewResource\ReviewResource;
use App\Filament\Concerns\HasDynamicBreadcrumbs;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

/**
 * @property-read \App\Filament\Admin\Resources\ReviewResource\ReviewResource $resource
 */
class ManageReviews extends ManageRecords
{
    use HasMobilePagination;
    use HasDynamicBreadcrumbs;
    protected static string $resource = ReviewResource::class;

    public function getTitle(): string
    {
        return static::$title ?? static::getResource()::getTitleCasePluralModelLabel();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ExportAction::make()
                ->exporter(ReviewExporter::class)
                ->label(__('Ekspor Data'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success'),
            Actions\CreateAction::make()
                ->label(__('Tambah Review'))
                ->icon('heroicon-o-plus')
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title(__('Review Ditambahkan'))
                        ->body(__('Review baru telah berhasil ditambahkan.'))
                ),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            ...$this->breadcrumbParentCrumb(),
            ReviewResource::getUrl('index') => ReviewResource::getNavigationLabel(),
        ];
    }
}
