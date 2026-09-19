<?php

namespace App\Filament\Admin\Resources\PackageResource\Pages\EditPackage;

use App\Filament\Admin\Resources\PackageResource\PackageResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPackage extends EditRecord
{
    protected static string $resource = PackageResource::class;

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title(__('Paket Diperbarui'))
            ->body(__('Paket telah berhasil diperbarui.'));
    }

    public function getTitle(): string
    {
        return $this->record->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label(__('Kembali'))
                ->url(fn () => static::getResource()::getUrl('index'))
                ->color('gray')->button()
                ->icon('heroicon-o-arrow-left'),

            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
