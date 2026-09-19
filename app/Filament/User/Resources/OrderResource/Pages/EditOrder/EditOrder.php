<?php

namespace App\Filament\User\Resources\OrderResource\Pages\EditOrder;

use App\Filament\User\Resources\OrderResource\OrderResource;
use App\Helpers\NativeNotificationHelper\NativeNotificationHelper;
use Filament\Actions;
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
        NativeNotificationHelper::success(__('Pesanan berhasil diperbarui.'));
    }

    protected function getFormActions(): array
    {
        return [];
    }
}
