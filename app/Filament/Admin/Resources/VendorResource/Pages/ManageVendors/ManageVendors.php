<?php

namespace App\Filament\Admin\Resources\VendorResource\Pages\ManageVendors;

use App\Filament\Admin\Resources\VendorResource\VendorResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

/**
 * @property-read \App\Filament\Admin\Resources\VendorResource\VendorResource $resource
 */
class ManageVendors extends ManageRecords
{
    protected static string $resource = VendorResource::class;

    public function getTitle(): string
    {
        return static::$title ?? static::getResource()::getTitleCasePluralModelLabel();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label(__('Tambah Vendor'))
                ->icon('heroicon-o-plus')
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title(__('Vendor Ditambahkan'))
                        ->body(__('Vendor baru telah berhasil ditambahkan.'))
                ),
        ];
    }
}
