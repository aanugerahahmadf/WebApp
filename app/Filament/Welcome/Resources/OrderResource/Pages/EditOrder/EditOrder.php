<?php

namespace App\Filament\Welcome\Resources\OrderResource\Pages\EditOrder;

use App\Filament\Welcome\Resources\OrderResource\OrderResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label(__('Kembali'))
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => request()->query('from') === 'view'
                    ? OrderResource::getUrl('view', ['record' => $this->record])
                    : OrderResource::getUrl('index'))
                ->extraAttributes(['wire:navigate' => true]),

            Actions\Action::make('view')
                ->label(__('Lihat Detail'))
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->url(fn () => OrderResource::getUrl('view', ['record' => $this->record]))
                ->extraAttributes(['wire:navigate' => true]),
        ];
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return __('Pesanan berhasil diperbarui.');
    }

    protected function afterSave(): void
    {
        Notification::make()
            ->title(__('Berhasil!'))
            ->body(__('Pesanan berhasil diperbarui.'))
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [];
    }
}
