<?php

namespace App\Filament\Admin\Resources\WishlistResource\Pages\ManageWishlists;

use App\Filament\Admin\Concerns\HasMobilePagination\HasMobilePagination;
use App\Filament\Admin\Exports\WishlistExporter\WishlistExporter;
use App\Filament\Admin\Pages\Home\Home;
use App\Filament\Admin\Resources\WishlistResource\WishlistResource;
use App\Filament\Concerns\HasDynamicBreadcrumbs;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

/**
 * @property-read \App\Filament\Admin\Resources\WishlistResource\WishlistResource $resource
 */
class ManageWishlists extends ManageRecords
{
    use HasMobilePagination;
    use HasDynamicBreadcrumbs;
    protected static string $resource = WishlistResource::class;

    public function getTitle(): string
    {
        return static::$title ?? static::getResource()::getTitleCasePluralModelLabel();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ExportAction::make()
                ->exporter(WishlistExporter::class)
                ->label(__('Ekspor Data'))
                ->icon('heroicon-s-arrow-down-tray')
                ->color('success'),
            Actions\CreateAction::make()
                ->label(__('Tambah Wishlist'))
                ->icon('heroicon-s-plus')
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title(__('Wishlist Ditambahkan'))
                        ->body(__('Wishlist baru telah berhasil ditambahkan.'))
                ),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            ...$this->breadcrumbParentCrumb(),
            WishlistResource::getUrl('index') => WishlistResource::getNavigationLabel(),
        ];
    }
}
