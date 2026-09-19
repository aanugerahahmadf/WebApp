<?php

namespace App\Filament\User\Resources\OrderResource\Pages\ViewOrder;

use App\Enums\OrderPaymentStatus\OrderPaymentStatus;
use App\Enums\OrderStatus\OrderStatus;
use App\Filament\User\Pages\MessagesPage\MessagesPage;
use App\Filament\User\Resources\OrderResource\OrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    public function getTitle(): string
    {
        return __('Pesanan').' #'.($this->record->order_number ?? $this->record->id);
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);

        // Auto-sync: jika order cancelled tapi payment_status belum di-update
        if (
            $this->record->status === OrderStatus::CANCELLED
            && ! in_array($this->record->payment_status, [
                OrderPaymentStatus::CANCELLED,
                OrderPaymentStatus::REFUNDED,
                OrderPaymentStatus::PAID,
            ])
        ) {
            $this->record->updateQuietly(['payment_status' => OrderPaymentStatus::CANCELLED]);
            $this->record->refresh();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label(__('Kembali'))
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(function () {
                    $from = request()->query('from');
                    $inboxId = request()->query('inbox');

                    if ($from === 'messages') {
                        return $inboxId
                            ? MessagesPage::getUrl(['id' => $inboxId])
                            : MessagesPage::getUrl();
                    }

                    if ($from === 'view') {
                        return OrderResource::getUrl('view', ['record' => $this->record]);
                    }

                    return OrderResource::getUrl('index');
                })
                ->extraAttributes(['wire:navigate' => true]),

            Actions\EditAction::make()
                ->label(__('Edit Pesanan'))
                ->icon('heroicon-o-pencil-square')
                ->url(fn () => OrderResource::getUrl('edit', ['record' => $this->record, 'from' => 'view']))
                ->visible(fn () => in_array($this->record->status, [
                    OrderStatus::PENDING,
                    OrderStatus::CONFIRMED,
                    OrderStatus::COMPLETED,
                ])),
        ];
    }
}
