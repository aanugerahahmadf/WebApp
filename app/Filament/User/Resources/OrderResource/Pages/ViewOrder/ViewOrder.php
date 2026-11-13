<?php

namespace App\Filament\User\Resources\OrderResource\Pages\ViewOrder;

use App\Enums\OrderPaymentStatus\OrderPaymentStatus;
use App\Enums\OrderStatus\OrderStatus;
use App\Filament\User\Pages\Home\Home;
use App\Filament\User\Pages\MessagesPage\MessagesPage;
use App\Filament\User\Resources\OrderResource\OrderResource;
use App\Services\CourierTracking\OrderShippingSyncService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    public function getTitle(): string
    {
        return __('Pesanan').' #'.($this->record->order_number ?? $this->record->id);
    }

    public function getBreadcrumbs(): array
    {
        return [
            Home::getUrl() => __('Beranda'),
            ...parent::getBreadcrumbs(),
        ];
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
            Actions\Action::make('sync_tracking')
                ->label(__('Sinkronkan Pelacakan'))
                ->icon('/images/icons/refresh.svg')
                ->color('info')
                ->visible(fn () => filled($this->record->tracking_number) && filled($this->record->shipping_courier))
                ->action(function () {
                    $result = app(OrderShippingSyncService::class)->sync($this->record);
                    $this->record->refresh();
                    Notification::make()
                        ->title($result['synced'] ? __('Pelacakan disinkronkan') : __('Gagal sinkron pelacakan'))
                        ->body($result['message'] ?? null)
                        ->color($result['synced'] ? 'success' : 'danger')
                        ->send();
                }),
            Actions\EditAction::make()
                ->label(__('Edit Pesanan'))
                ->icon('/images/icons/pencil.svg')
                ->url(fn () => OrderResource::getUrl('edit', ['record' => $this->record, 'from' => 'view']))
                ->visible(fn () => in_array($this->record->status, [
                    OrderStatus::PENDING,
                    OrderStatus::CONFIRMED,
                    OrderStatus::COMPLETED,
                ])),
        ];
    }
}
