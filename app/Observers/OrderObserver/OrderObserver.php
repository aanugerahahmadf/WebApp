<?php

namespace App\Observers\OrderObserver;

use App\Enums\OrderPaymentStatus\OrderPaymentStatus;
use App\Enums\OrderStatus\OrderStatus;
use App\Models\History\History;
use App\Models\Order\Order;
use App\Services\PlatformNotificationService\PlatformNotificationService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    public function created(Order $order): void
    {
        History::create([
            'user_id' => $order->user_id,
            'type' => 'order',
            'transaction_id' => $order->id,
            'reference_number' => $order->order_number,
            'amount' => $order->total_price,
            'info' => $order->package?->name ?? __('Pemesanan Paket'),
            'status' => $order->status instanceof \BackedEnum ? $order->status->value : $order->status,
            'notes' => $order->notes,
            'created_at' => $order->created_at,
        ]);
    }

    public function updated(Order $order): void
    {
        if ($order->isDirty('status') && $order->status === OrderStatus::CANCELLED) {
            if (in_array($order->payment_status, [OrderPaymentStatus::PAID, OrderPaymentStatus::PARTIAL])) {
                $user = $order->user;
                if ($user) {
                    $user->increment('balance', $order->total_price);
                    $order->updateQuietly(['payment_status' => OrderPaymentStatus::REFUNDED]);

                    History::create([
                        'user_id' => $order->user_id,
                        'type' => 'balance',
                        'transaction_id' => $order->id,
                        'reference_number' => 'REF-'.$order->order_number,
                        'amount' => $order->total_price,
                        'info' => __('Refund Otomatis (Pembatalan Order #').$order->order_number.')',
                        'status' => 'success',
                    ]);

                    try {
                        [$refundTitle, $refundBody] = PlatformNotificationService::withRecipientLocale(
                            $user,
                            fn () => [
                                __('Refund Berhasil'),
                                __('Dana sebesar Rp :amount telah dikembalikan ke saldo Anda karena pembatalan Order #:order', [
                                    'amount' => number_format($order->total_price, 0, ',', '.'),
                                    'order' => $order->order_number,
                                ]),
                            ]
                        );

                        Notification::make()
                            ->title($refundTitle)
                            ->body($refundBody)
                            ->success()
                            ->sendToDatabase($user);

                        PlatformNotificationService::send($user, $refundTitle, $refundBody);
                    } catch (\Throwable $e) {
                        Log::warning('[OrderObserver] Notification failed: '.$e->getMessage());
                    }
                }
            } else {
                $order->updateQuietly(['payment_status' => OrderPaymentStatus::CANCELLED]);
            }
        }

        if ($order->isDirty('status')) {
            $user = $order->user;
            if ($user) {
                $statusLabel = $order->status instanceof OrderStatus
                    ? $order->status->getLabel()
                    : (is_string($order->status) ? $order->status : __('Tidak Diketahui'));

                $statusIcon = $order->status instanceof OrderStatus
                    ? $order->status->getIcon()
                    : 'heroicon-o-information-circle';

                try {
                    [$statusTitle, $statusBody] = PlatformNotificationService::withRecipientLocale(
                        $user,
                        fn () => [
                            __('Update Pesanan #:order', ['order' => $order->order_number]),
                            __('Status pesanan Anda kini: :status', ['status' => $statusLabel]),
                        ]
                    );

                    Notification::make()
                        ->title($statusTitle)
                        ->body($statusBody)
                        ->info()
                        ->icon($statusIcon)
                        ->sendToDatabase($user);

                    PlatformNotificationService::send($user, $statusTitle, $statusBody);
                } catch (\Throwable $e) {
                    Log::warning('[OrderObserver] Status notification failed: '.$e->getMessage());
                }
            }
        }

        History::updateOrCreate(
            ['type' => 'order', 'transaction_id' => $order->id],
            [
                'status' => $order->status instanceof \BackedEnum ? $order->status->value : (string) $order->status,
                'amount' => $order->total_price,
                'info' => $order->package?->name ?? __('Pemesanan Paket'),
                'notes' => $order->notes,
            ]
        );
    }

    public function deleting(Order $order): void
    {
        History::where('type', 'order')
            ->where('transaction_id', $order->id)
            ->update(['status' => 'cancelled']);
    }

    public function deleted(Order $order): void {}

    public function restored(Order $order): void
    {
        History::withTrashed()
            ->where('type', 'order')
            ->where('transaction_id', $order->id)
            ->restore();
    }

    public function forceDeleted(Order $order): void
    {
        History::withTrashed()
            ->where('type', 'order')
            ->where('transaction_id', $order->id)
            ->forceDelete();
    }
}
