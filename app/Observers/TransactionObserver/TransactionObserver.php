<?php

namespace App\Observers\TransactionObserver;

use App\Models\History\History;
use App\Models\Transaction\Transaction;
use App\Services\PlatformNotificationService\PlatformNotificationService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class TransactionObserver
{
    public function created(Transaction $transaction): void
    {
        $this->logToHistory($transaction);
    }

    public function updated(Transaction $transaction): void
    {
        $this->logToHistory($transaction);

        if ($transaction->status->value === 'success' && $transaction->getOriginal('status') !== 'success') {
            if ($transaction->type->value === 'topup') {
                $user = $transaction->user;
                if ($user) {
                    $user->increment('balance', $transaction->amount);

                    try {
                        [$topupTitle, $topupBody] = PlatformNotificationService::withRecipientLocale(
                            $user,
                            fn () => [
                                __('Topup Berhasil'),
                                __('Saldo sebesar Rp :amount telah masuk ke akun Anda.', [
                                    'amount' => number_format($transaction->amount, 0, ',', '.'),
                                ]),
                            ]
                        );

                        Notification::make()
                            ->title($topupTitle)
                            ->body($topupBody)
                            ->success()
                            ->icon('heroicon-o-banknotes')
                            ->sendToDatabase($user);

                        PlatformNotificationService::send($user, $topupTitle, $topupBody);
                    } catch (\Throwable $e) {
                        Log::warning('[TransactionObserver] Notification failed: '.$e->getMessage());
                    }
                }
            }
        }
    }

    protected function logToHistory(Transaction $transaction): void
    {
        History::updateOrCreate(
            ['type' => $transaction->type->value, 'transaction_id' => $transaction->id],
            [
                'user_id' => $transaction->user_id,
                'reference_number' => $transaction->reference_number,
                'status' => $transaction->status->value,
                'amount' => $transaction->total_amount,
                'notes' => $transaction->notes,
                'info' => $transaction->payment_method ?? ucfirst($transaction->type->value),
                'created_at' => $transaction->created_at,
            ]
        );
    }
}
