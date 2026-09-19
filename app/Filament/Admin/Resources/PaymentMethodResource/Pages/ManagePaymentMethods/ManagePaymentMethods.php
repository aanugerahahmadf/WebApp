<?php

namespace App\Filament\Admin\Resources\PaymentMethodResource\Pages\ManagePaymentMethods;

use App\Filament\Admin\Resources\PaymentMethodResource\PaymentMethodResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManagePaymentMethods extends ManageRecords
{
    protected static string $resource = PaymentMethodResource::class;

    public function getTitle(): string
    {
        return static::$title ?? static::getResource()::getTitleCasePluralModelLabel();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label(__('Tambah Metode Pembayaran'))
                ->icon('heroicon-o-plus')
                ->mutateFormDataUsing(function (array $data): array {
                    if (empty($data['sort_order'])) {
                        $data['sort_order'] = PaymentMethodResource::getModel()::max('sort_order') + 1;
                    }
                    return $data;
                })
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title(__('Metode Pembayaran Ditambahkan'))
                        ->body(__('Metode pembayaran baru berhasil ditambahkan.'))
                ),
        ];
    }
}
