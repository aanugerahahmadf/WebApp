<?php

namespace App\Filament\Admin\Resources\HelpResource\Pages\ManageHelps;

use App\Filament\Admin\Resources\HelpResource\HelpResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageHelps extends ManageRecords
{
    protected static string $resource = HelpResource::class;

    public function getTitle(): string
    {
        return static::$title ?? static::getResource()::getTitleCasePluralModelLabel();
    }

    protected function getHeaderActions(): array
    {
        return [
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
}
