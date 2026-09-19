<?php

namespace App\Services\MidtransService;

use App\Enums\OrderPaymentStatus\OrderPaymentStatus;
use App\Models\Transaction\Transaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MidtransService
{
    private const PAYMENT_TYPES = [
        'bank_transfer' => ['bca_va', 'bni_va', 'bri_va', 'permata_va'],
        'e_wallet' => [],
        'qris' => ['qris'],
        'credit_card' => ['credit_card'],
    ];

    public function isEnabled(): bool
    {
        return config('midtrans.enabled', false)
            && config('midtrans.server_key') !== ''
            && config('midtrans.server_key') !== null;
    }

    /**
     * E-wallet / QRIS / Card -> Midtrans Snap enabled_payments.
     */
    public function enabledPayments(string $pmType, ?string $pmCode = null): array
    {
        if ($pmType === 'e_wallet') {
            // Midtrans hanya mendukung 4 e-wallet ini; kode lain -> default GoPay.
            $allowed = ['gopay', 'ovo', 'dana', 'shopeepay'];

            return in_array($pmCode, $allowed, true) ? [$pmCode] : ['gopay'];
        }

        return self::PAYMENT_TYPES[$pmType] ?? ['credit_card'];
    }

    /**
     * Create a Midtrans Snap token for the given transaction + payment method.
     * Persists payment_url on the transaction.
     *
     * @return array{token: string, redirect_url: string}|null
     */
    public function createSnapToken(Transaction $transaction, string $pmType, ?string $pmCode = null): ?array
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $order = $transaction->order;
        $user = $transaction->user;
        if (! $order || ! $user) {
            Log::error('[Midtrans] Missing order/user for transaction #'.$transaction->id);

            return null;
        }

        $gross = (int) round($transaction->total_amount);
        $enabled = $this->enabledPayments($pmType, $pmCode);

        $params = [
            'transaction_details' => [
                'order_id' => $transaction->reference_number,
                'gross_amount' => $gross,
            ],
            'item_details' => $this->buildItemDetails($order, $transaction),
            'customer_details' => [
                'first_name' => $user->name,
                'email' => $user->email,
                'phone' => $user->whatsapp ?? $user->phone ?? '',
            ],
            'enabled_payments' => $enabled,
            'credit_card' => [
                'secure' => config('midtrans.is_3ds'),
            ],
        ];

        try {
            $response = Http::withBasicAuth(config('midtrans.server_key'), '')
                ->acceptJson()
                ->post(config('midtrans.snap_url'), $params);

            if (! $response->successful()) {
                Log::error('[Midtrans] Snap API error ('.$response->status().'): '.$response->body());

                return null;
            }

            $data = $response->json();
            $token = $data['token'] ?? null;
            if (! $token) {
                Log::error('[Midtrans] Snap response tanpa token: '.$response->body());

                return null;
            }

            $redirectUrl = config('midtrans.snap_web_url').$token;

            $transaction->update([
                'payment_gateway' => 'midtrans',
                'payment_url' => $redirectUrl,
                'status' => 'pending',
                'metadata' => array_merge($transaction->metadata ?? [], [
                    'snap_token' => $token,
                    'snap_enabled_payments' => $enabled,
                ]),
            ]);

            return ['token' => $token, 'redirect_url' => $redirectUrl];
        } catch (\Throwable $e) {
            Log::error('[Midtrans] Failed to get snap token: '.$e->getMessage(), [
                'transaction_id' => $transaction->id,
                'reference' => $transaction->reference_number,
            ]);

            return null;
        }
    }

    /**
     * Handle a Midtrans webhook notification and reconcile the Transaction/Order.
     */
    public function handleNotification(array $payload): bool
    {
        $reference = $payload['order_id'] ?? null;
        if (! $reference) {
            return false;
        }

        $transaction = Transaction::where('reference_number', $reference)->first();
        if (! $transaction) {
            return false;
        }

        $order = $transaction->order;
        if (! $order) {
            return false;
        }

        if (! $this->verifySignature($payload)) {
            Log::warning('[Midtrans] Signature tidak valid untuk '.$reference);

            return false;
        }

        $transactionStatus = $payload['transaction_status'] ?? null;
        $fraudStatus = $payload['fraud_status'] ?? null;
        $paymentType = $payload['payment_type'] ?? $transaction->payment_gateway;

        // Sukses bila settlement/capture/success (dan fraud accept untuk kartu)
        $isSuccess = in_array($transactionStatus, ['capture', 'settlement', 'success'])
            && in_array($fraudStatus, [null, 'accept']);

        if ($isSuccess) {
            $transaction->update([
                'status' => 'success',
                'paid_at' => now(),
                'payment_gateway' => 'midtrans',
                'payment_method' => $paymentType ?? $transaction->payment_method,
                'metadata' => array_merge($transaction->metadata ?? [], [
                    'midtrans_payload' => $payload,
                ]),
            ]);

            $order->update([
                'payment_status' => OrderPaymentStatus::PAID,
            ]);

            return true;
        }

        if (in_array($transactionStatus, ['expire', 'cancel', 'deny'])) {
            $transaction->update([
                'status' => 'failed',
                'metadata' => array_merge($transaction->metadata ?? [], [
                    'midtrans_payload' => $payload,
                ]),
            ]);
            $order->update([
                'payment_status' => OrderPaymentStatus::FAILED,
            ]);
        } elseif ($transactionStatus === 'pending') {
            $order->update(['payment_status' => OrderPaymentStatus::PENDING]);
        }

        return true;
    }

    private function verifySignature(array $data): bool
    {
        if (! config('midtrans.is_production')) {
            // Sandbox: tetap verifikasi bila signature tersedia
        }

        $rawSignature = ($data['signature_key'] ?? null)
            ?? ($data['original_signature_key'] ?? null);
        if ($rawSignature) {
            $string = (string) ($data['order_id'] ?? '')
                .' '.(string) ($data['status_code'] ?? '')
                .' '.(string) ($data['gross_amount'] ?? '').' '
                .config('midtrans.server_key');

            return hash_equals($rawSignature, base64_encode(hash('sha512', $string, true)));
        }

        return true;
    }

    private function buildItemDetails($order, Transaction $transaction): array
    {
        $name = $order->package?->name ?? $order->product?->name ?? ('Pesanan #'.$order->order_number);
        $qty = max(1, (int) ($order->quantity ?? 1));
        $price = (int) round($transaction->total_amount / $qty);

        return [
            [
                'id' => 'ORDER-'.$order->id,
                'price' => $price,
                'quantity' => $qty,
                'name' => mb_substr($name, 0, 50),
            ],
        ];
    }
}