<?php

namespace App\Filament\Admin\Resources\ReferenceOptionResource\Pages\ManageReferenceOptions;

use App\Filament\Admin\Resources\ReferenceOptionResource\ReferenceOptionResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageReferenceOptions extends ManageRecords
{
    protected static string $resource = ReferenceOptionResource::class;

    public function getTitle(): string
    {
        return static::$title ?? static::getResource()::getTitleCasePluralModelLabel();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label(__('Tambah Opsi'))
                ->icon('heroicon-o-plus')
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title(__('Opsi Ditambahkan'))
                        ->body(__('Opsi referensi baru telah berhasil ditambahkan.'))
                ),
        ];
    }
}
