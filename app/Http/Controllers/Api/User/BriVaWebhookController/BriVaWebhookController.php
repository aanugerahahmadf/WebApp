<?php

namespace App\Http\Controllers\Api\User\BriVaWebhookController;

use App\Http\Controllers\Api\User\OrderController\OrderController;
use App\Http\Controllers\Controller;
use App\Models\Transaction\Transaction;
use App\Services\BriService\BriService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Notifikasi pembayaran BRI (Virtual Account dan QRIS dinamis).
 *
 * Dua jalur yang harus tetap hidup:
 *
 * 1. VOID/VA notification mengirim datanya di dalam `virtualAccountData`
 *    (bukan di root). Kalau hanya root yang dibaca, `virtualAccountNo` selalu
 *    kosong -> transaksi tidak ditemukan -> pesanan tidak pernah LUNAS walau
 *    BRI sudah mengirim uang. Karena itu payload dinormalisasi dulu di sini.
 *
 * 2. BRI mengirim ulang notifikasi sampai dijawab HTTP 200. Semua kondisi
 *    (signature salah, nominal beda, transaksi tidak dikenal) tetap dijawab
 *    200 supaya BRI berhenti mengirim ulang, tetapi HANYA status sukses yang
 *    mengubah transaksi menjadi LUNAS.
 */
class BriVaWebhookController extends Controller
{
    public function notification(Request $request, BriService $bri): JsonResponse
    {
        $rawBody = $request->getContent();

        Log::info('[BRI-VA] Notifikasi pembayaran diterima', [
            'timestamp_present' => $request->hasHeader('X-TIMESTAMP'),
            'signature_present' => $request->hasHeader('X-SIGNATURE'),
        ]);

        $payload = json_decode($rawBody, true);
        if (! is_array($payload)) {
            return $this->ack('Invalid JSON body');
        }

        if (! $this->signatureIsValid($request, $bri, $rawBody)) {
            return $this->ack('Invalid signature');
        }

        $data = $this->virtualAccountData($payload);

        // Nomor VA bisa berada di root (legacy) atau di `virtualAccountData`
        // (SNAP). partnerServiceId padding berupa spasi dibuang supaya
        // perbandingan dengan kolom `virtual_account_no` tetap cocok.
        $vaNumber = $this->normalizeVaNumber(
            (string) ($data['virtualAccountNo'] ?? $payload['virtualAccountNo'] ?? '')
        );
        $reference = (string) (
            $data['partnerReferenceNo']
            ?? $payload['originalPartnerReferenceNo']
            ?? $payload['partnerReferenceNo']
            ?? ''
        );
        $trxId = (string) ($data['trxId'] ?? $payload['trxId'] ?? '');
        $paidAmount = $this->amountOf($payload, $data);
        $status = $this->statusOf($payload, $data, 'PAID');

        $transaction = $this->findPendingTransaction($vaNumber, $reference, $trxId);

        if (! $transaction) {
            Log::warning('[BRI-VA] Transaksi (VA '.$vaNumber.' / ref '.$reference.') tidak ditemukan atau sudah dibayar.');

            return $this->ack('Transaction not found');
        }

        if (! $this->amountMatches($transaction, $paidAmount)) {
            Log::warning('[BRI-VA] Nominal tidak cocok untuk VA '.$vaNumber.'. Diterima: '.$paidAmount.', diharapkan: '.$transaction->total_amount);

            return $this->ack('Amount mismatch');
        }

        // Tanpa nomor VA berarti ini notifikasi QRIS yang dikirim ke endpoint
        // VA (sebagian merchant mengarahkan keduanya ke URL yang sama).
        $isQris = $vaNumber === '';

        if ($this->isSuccessStatus($status, $paidAmount)) {
            $this->settle($transaction, $payload, $isQris);
        } else {
            Log::info('[BRI-VA] Status notifikasi bukan sukses untuk transaksi #'.$transaction->id.': '.$status);
        }

        // BRI mengharapkan HTTP 200 agar tidak mengirim ulang.
        return $this->ack('ok');
    }

    /**
     * QRIS MPM Dinamis Payment Notification callback dari BRI.
     */
    public function qrisNotification(Request $request, BriService $bri): JsonResponse
    {
        $rawBody = $request->getContent();

        Log::info('[BRI-QRIS] Notifikasi pembayaran QRIS diterima', [
            'signature' => $request->header('X-SIGNATURE'),
            'timestamp' => $request->header('X-TIMESTAMP'),
            'body' => $rawBody,
        ]);

        $payload = json_decode($rawBody, true);
        if (! is_array($payload)) {
            return $this->ack('Invalid JSON body');
        }

        if (! $this->signatureIsValid($request, $bri, $rawBody)) {
            return $this->ack('Invalid signature');
        }

        $data = $this->virtualAccountData($payload);

        $reference = (string) (
            $payload['originalPartnerReferenceNo']
            ?? $payload['partnerReferenceNo']
            ?? $data['partnerReferenceNo']
            ?? ''
        );
        $paidAmount = $this->amountOf($payload, $data);
        $status = $this->statusOf($payload, $data, '');

        $transaction = $this->findPendingTransaction('', $reference, '');

        if (! $transaction) {
            Log::warning('[BRI-QRIS] Transaksi (ref '.$reference.') tidak ditemukan atau sudah dibayar.');

            return $this->ack('Transaction not found');
        }

        if (! $this->amountMatches($transaction, $paidAmount)) {
            Log::warning('[BRI-QRIS] Nominal tidak cocok. Diterima: '.$paidAmount.', diharapkan: '.$transaction->total_amount);

            return $this->ack('Amount mismatch');
        }

        if ($this->isSuccessStatus($status, $paidAmount)) {
            $this->settle($transaction, $payload, true);
        } else {
            Log::info('[BRI-QRIS] Status notifikasi bukan sukses untuk transaksi #'.$transaction->id.': '.$status);
        }

        return $this->ack('ok');
    }

    /**
     * Verifikasi signature hanya bila memang diwajibkan.
     *
     * Konfigurasi `webhook_require_signature` (default true) berlaku mutlak;
     * kalau dimatikan dan SNAP juga tidak aktif (mode simulasi lokal), body
     * diterima apa adanya supaya alur uji coba tetap bisa berjalan.
     */
    private function signatureIsValid(Request $request, BriService $bri, string $rawBody): bool
    {
        if (! config('bri.webhook_require_signature', true) && ! $bri->snapEnabled()) {
            return true;
        }

        return $bri->verifyVaNotification(
            (string) $request->header('X-TIMESTAMP', ''),
            (string) $request->header('X-SIGNATURE', ''),
            $rawBody
        );
    }

    /**
     * Data pembayaran BRI SNAP dibungkus `virtualAccountData`; payload lama
     * menaruhnya langsung di root. Keduanya harus dibaca.
     *
     * @return array<string, mixed>
     */
    private function virtualAccountData(array $payload): array
    {
        $data = $payload['virtualAccountData'] ?? null;

        return is_array($data) ? $data : [];
    }

    private function normalizeVaNumber(string $number): string
    {
        return (string) preg_replace('/\s+/', '', trim($number));
    }

    /**
     * Ambil nominal dari berbagai bentuk payload BRI.
     */
    private function amountOf(array $payload, array $data): float
    {
        $candidates = [
            $payload['paidAmount'] ?? null,
            $data['paidAmount'] ?? null,
            $payload['totalAmount'] ?? null,
            $data['totalAmount'] ?? null,
            $payload['amount'] ?? null,
            $data['amount'] ?? null,
        ];

        foreach ($candidates as $amount) {
            if (is_array($amount) && array_key_exists('value', $amount) && is_numeric($amount['value'])) {
                return (float) $amount['value'];
            }

            if (is_numeric($amount)) {
                return (float) $amount;
            }
        }

        return 0.0;
    }

    /**
     * Status transaksi: bisa di `additionalInfo`, root, atau virtualAccountData.
     */
    private function statusOf(array $payload, array $data, string $default): string
    {
        $candidates = [
            $payload['additionalInfo']['transactionStatus'] ?? null,
            $payload['transactionStatus'] ?? null,
            $payload['latestTransactionStatus'] ?? null,
            $payload['paymentFlagStatus'] ?? null,
            $data['transactionStatus'] ?? null,
            $data['latestTransactionStatus'] ?? null,
            $data['paymentFlagStatus'] ?? null,
        ];

        foreach ($candidates as $status) {
            if (is_string($status) && trim($status) !== '') {
                return trim($status);
            }
        }

        return $default;
    }

    /**
     * Status BRI yang berarti pembayaran berhasil.
     *
     * Nilai yang beredar: "00" (SNAP), "PAID" (legacy), "SUCCESS"/"SETTLED"
     * (beberapa kanal). Kalau status tidak dikirim sama sekali, nominal > 0
     * dianggap bukti pembayaran (payload notifikasi memang hanya dikirim
     * setelah uang masuk).
     */
    private function isSuccessStatus(string $status, float $paidAmount): bool
    {
        $normalized = strtoupper(trim($status));

        if ($normalized === '') {
            return $paidAmount > 0;
        }

        return in_array($normalized, ['00', '0000', 'PAID', 'SUCCESS', 'SETTLED', 'BERHASIL'], true);
    }

    private function amountMatches(Transaction $transaction, float $paidAmount): bool
    {
        if ($paidAmount <= 0) {
            return true;
        }

        $tolerance = (float) config('bri.amount_tolerance', 500);

        return abs($paidAmount - (float) $transaction->total_amount) <= $tolerance;
    }

    /**
     * Cari transaksi pending berdasarkan nomor VA, referensi, atau trxId.
     *
     * Nomor VA adalah kunci utama. Referensi/trxId hanya cadangan untuk
     * notifikasi yang tidak menyertakan nomor VA (mis. QRIS).
     */
    private function findPendingTransaction(string $vaNumber, string $reference, string $trxId): ?Transaction
    {
        $pending = static fn (): Builder => Transaction::query()
            ->whereIn('status', ['pending', 'processing'])
            ->latest('id');

        if ($vaNumber !== '') {
            $transaction = $pending()->where('virtual_account_no', $vaNumber)->first();
            if ($transaction) {
                return $transaction;
            }

            // Nomor tersimpan bisa mengandung spasi (padding partnerServiceId).
            $transaction = $pending()
                ->whereRaw("REPLACE(virtual_account_no, ' ', '') = ?", [$vaNumber])
                ->first();
            if ($transaction) {
                return $transaction;
            }
        }

        if ($reference !== '') {
            $transaction = $pending()->where('reference_number', $reference)->first();
            if ($transaction) {
                return $transaction;
            }
        }

        if ($trxId !== '') {
            $transaction = $pending()->where('reference_number', $trxId)->first();
            if ($transaction) {
                return $transaction;
            }
        }

        return null;
    }

    /**
     * Tandai transaksi LUNAS + pesanannya PAID, lalu kirim notifikasi.
     *
     * `markAsSuccess()` idempotent (tidak menambah saldo dua kali dan tidak
     * mengubah order yang sudah PAID), jadi callback ganda aman.
     */
    private function settle(Transaction $transaction, array $payload, bool $isQris): void
    {
        $transaction->markAsSuccess();

        $transaction->update([
            'payment_method' => $transaction->payment_method ?: ($isQris ? 'QRIS' : 'Virtual Account BRI'),
            'notes' => $isQris
                ? __('Pembayaran QRIS otomatis terkonfirmasi')
                : __('Pembayaran Virtual Account BRI otomatis terkonfirmasi'),
            'metadata' => array_merge($transaction->metadata ?? [], [
                ($isQris ? 'bri_qris_notification' : 'bri_va_notification') => $payload,
            ]),
        ]);

        if ($transaction->order) {
            try {
                app(OrderController::class)->sendPaymentNotifications($transaction->order->fresh());
            } catch (\Throwable $e) {
                Log::warning('[BRI] Gagal mengirim notifikasi pembayaran: '.$e->getMessage());
            }
        }

        Log::info('[BRI] Transaksi #'.$transaction->id.' ditandai LUNAS via notifikasi '.($isQris ? 'QRIS' : 'BRI-VA').'.');
    }

    private function ack(string $message): JsonResponse
    {
        return response()->json([
            'responseCode' => '2009700',
            'responseMessage' => 'Successful',
            'info' => $message,
        ], 200);
    }
}
