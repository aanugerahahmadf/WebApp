<?php

namespace App\Filament\Admin\Resources\ProductResource\Pages\EditProduct;

use App\Filament\Admin\Resources\ProductResource\ProductResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title(__('Bunga Diperbarui'))
            ->body(__('Bunga telah berhasil diperbarui.'));
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

            Actions\DeleteAction::make(),
        ];
    }
}
