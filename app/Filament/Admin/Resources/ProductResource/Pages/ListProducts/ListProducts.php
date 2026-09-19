<?php

namespace App\Filament\Admin\Resources\ProductResource\Pages\ListProducts;

use App\Filament\Admin\Exports\ProductExporter\ProductExporter;
use App\Filament\Admin\Resources\ProductResource\ProductResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    public function getTitle(): string
    {
        return static::$title ?? static::getResource()::getTitleCasePluralModelLabel();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ExportAction::make()
                ->exporter(ProductExporter::class)
                ->label(__('Ekspor Data'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success'),
            Actions\CreateAction::make()
                ->label(__('Tambah Product'))
                ->icon('heroicon-o-plus')
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title(__('Product Ditambahkan'))
                        ->body(__('Product baru telah berhasil ditambahkan.'))
                ),
        ];
    }
}
