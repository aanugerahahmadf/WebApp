<?php

namespace App\Filament\Admin\Resources\WishlistResource\Pages\ManageWishlists;

use App\Filament\Admin\Exports\WishlistExporter\WishlistExporter;
use App\Filament\Admin\Resources\WishlistResource\WishlistResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

/**
 * @property-read \App\Filament\Admin\Resources\WishlistResource\WishlistResource $resource
 */
class ManageWishlists extends ManageRecords
{
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
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success'),
            Actions\CreateAction::make()
                ->label(__('Tambah Wishlist'))
                ->icon('heroicon-o-plus')
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title(__('Wishlist Ditambahkan'))
                        ->body(__('Wishlist baru telah berhasil ditambahkan.'))
                ),
        ];
    }
}
