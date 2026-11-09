<?php

namespace App\Jobs\PushToAppsScript;

use App\Services\GoogleAppsScript\AppsScriptException;
use App\Services\GoogleAppsScript\GoogleAppsScriptClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Kirim satu batch record ke Google Apps Script di luar request cycle.
 *
 * Kenapa lewat queue: Apps Script membalas dalam 1-6 detik dan bisa kena kuota.
 * Menaruhnya di jalur request pengguna membuat checkout/layar order terasa
 * lambat dan bisa gagal bareng.
 *
 * Aturan kegagalan:
 *  - `bad_signature` / `stale_request` / `replayed_nonce` -> jangan retry,
 *    percuma; catat dan lanjut. Penyebabnya konfigurasi, bukan lalu lintas.
 *  - `not_configured` -> integrasi memang dimatikan; diam-diam lewati.
 *  - sisanya (timeout, 5xx, handler_error) -> retry sesuai `$tries`.
 *
 * Kegagalan job TIDAK boleh membatalkan transaksi bisnis yang sudah selesai di
 * Laravel: ini saluran mirror.
 */
class PushToAppsScript implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 90;

    /**
     * @param  string  $action  aksi registry, mis. 'orders.rows.append'
     * @param  array<string,mixed>  $payload  payload siap-tanda-tangan
     * @param  string|null  $label  penanda manusia untuk log ('order #123'), tanpa data sensitif
     */
    public function __construct(
        public string $action,
        public array $payload = [],
        public ?string $label = null,
    ) {}

    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function handle(): void
    {
        // Dibaca ulang tiap jalan, bukan disuntik: konstruktornya berskala
        // skalar sehingga tidak bisa di-resolve otomatis oleh container.
        $client = GoogleAppsScriptClient::make();

        if (! $client->isConfigured()) {
            // Integrasi dimatikan lewat .env; bukan kegagalan.
            return;
        }

        try {
            $result = $client->push($this->action, $this->payload);

            Log::info('Apps Script sinkron', [
                'action' => $this->action,
                'label' => $this->label,
                'written' => $result['written'] ?? $result['inserted'] ?? $result['updated'] ?? 0,
            ]);
        } catch (AppsScriptException $exception) {
            if ($exception->errorCode() === 'not_configured') {
                return;
            }

            if ($exception->isSignatureProblem()) {
                // Fail cepat: retry tidak memperbaiki secret/jam yang salah.
                Log::error('Apps Script menolak tanda tangan', [
                    'action' => $this->action,
                    'label' => $this->label,
                    'code' => $exception->errorCode(),
                    'request_id' => $exception->requestId(),
                    'petunjuk' => 'Bandingkan GOOGLE_APPS_SCRIPT_SECRET (.env) dengan WFD_SHARED_SECRET (Script Properties) dan jam server.',
                ]);

                // InteractsWithQueue::fail() diam saja kalau job tidak sedang
                // berjalan di worker (mis. dispatchSync atau test). Tanpa guard
                // ini, salah secret menjadi kegagalan hening.
                if ($this->job) {
                    $this->fail($exception);

                    return;
                }

                throw $exception;
            }

            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Apps Script sinkron gugur permanen', [
            'action' => $this->action,
            'label' => $this->label,
            'error' => $exception->getMessage(),
        ]);
    }
}
