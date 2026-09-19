<?php

namespace App\Filament\Admin\Resources\OrderResource\Pages\ViewOrder;

use App\Enums\OrderPaymentStatus\OrderPaymentStatus;
use App\Enums\OrderStatus\OrderStatus;
use App\Filament\Admin\Pages\MessagesPage\MessagesPage;
use App\Filament\Admin\Resources\OrderResource\OrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

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

    public function getTitle(): string
    {
        return (string) ($this->record->order_number ?? '');
    }

    protected function getHeaderActions(): array
    {
        $fromMessages = request()->query('from') === 'messages';
        $inboxId = request()->query('inbox');

        if ($fromMessages && $inboxId) {
            $backUrl = MessagesPage::getUrl().'/'.$inboxId;
            $backLabel = __('Kembali ke Pesan');
        } elseif ($fromMessages) {
            $backUrl = MessagesPage::getUrl();
            $backLabel = __('Kembali ke Pesan');
        } else {
            $backUrl = OrderResource::getUrl('index');
            $backLabel = __('Kembali ke Pesanan');
        }

        return [
            Actions\Action::make('back')
                ->label($backLabel)
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url($backUrl),

            Actions\EditAction::make()
                ->url(fn () => OrderResource::getUrl('edit', ['record' => $this->record, 'from' => 'view'])),
        ];
    }
}
