<?php

namespace App\Services\GoogleAppsScript;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Client tipis untuk Web App Google Apps Script proyek ini.
 *
 * Satu Web App URL dipakai bersama oleh Laravel, Flutter, Kotlin, dan AI Core
 * (lihat apps_script/README.md). Kontrak yang perlu diingat saat memakai kelas
 * ini:
 *
 *  1. Apps Script TIDAK membaca HTTP header, jadi secret dikirim di body lewat
 *     AppsScriptSignature -- jangan menambahkan `withToken()` di sini.
 *  2. Respons SELALU HTTP 200. Keberhasilan dibaca dari `ok`, bukan status.
 *  3. Nonce sekali pakai: percobaan ulang WAJIB memakai nonce baru, kalau tidak
 *     permintaan yang sebenarnya sudah tersimpan akan ditolak `replayed_nonce`.
 *
 * Data apa pun yang dikirim ke sini adalah SALINAN. Laravel tetap sumber
 * kebenaran (AGENTS.md §2), dan kegagalan Apps Script tidak boleh menggagalkan
 * alur bisnis: pakai `pushQuietly()` pada jalur request pengguna, `push()` pada
 * job/queue yang boleh retry.
 */
class GoogleAppsScriptClient
{
    public const SOURCE = 'laravel';

    protected const MAX_BATCH = 500;

    public function __construct(
        protected string $execUrl = '',
        protected string $secret = '',
        protected string $readKey = '',
        protected string $source = self::SOURCE,
        protected int $timeoutSeconds = 30,
        protected int $attempts = 2,
    ) {}

    /**
     * Bangun client dari `config('services.google_apps_script.*')`.
     */
    public static function make(): static
    {
        return new static(
            execUrl: (string) config('services.google_apps_script.exec_url', ''),
            secret: (string) config('services.google_apps_script.secret', ''),
            readKey: (string) config('services.google_apps_script.read_key', ''),
            source: (string) config('services.google_apps_script.source', self::SOURCE),
            timeoutSeconds: (int) config('services.google_apps_script.timeout', 30),
            attempts: max(1, (int) config('services.google_apps_script.attempts', 2)),
        );
    }

    public function isConfigured(): bool
    {
        return $this->execUrl !== '' && $this->secret !== '' && Str::startsWith($this->execUrl, 'https://');
    }

    /**
     * Sinkron batch besar dipisah supaya tidak menabrak batas Apps Script.
     */
    public static function chunks(array $records): array
    {
        return array_chunk($records, self::MAX_BATCH);
    }

    /**
     * Tambah record. `$action` adalah kunci registry Apps Script, mis. 'orders.rows'.
     *
     * @param  array<int|string, mixed>  $records  satu record atau daftar record
     * @return array{tab:string,written?:int,requested?:int}
     */
    public function append(string $action, array $records, array $options = []): array
    {
        return $this->push($action.'.append', $this->payloadFor($records, $options));
    }

    /**
     * Upsert berdasarkan kolom kunci registry.
     */
    public function upsert(string $action, array $records, string $keyColumn, array $options = []): array
    {
        return $this->push($action.'.update', $this->payloadFor($records, $options + ['keyColumn' => $keyColumn]));
    }

    /**
     * Aksi bebas, mis. 'forms.leads.submit' atau 'notifications.email'.
     */
    public function action(string $action, array $payload = []): array
    {
        return $this->push($action, $payload);
    }

    /**
     * Versi tidak melempar untuk jalur request pengguna. Kegagalan dicatat dan
     * dikembalikan sebagai array supaya pemanggil bisa memilih menampilkan
     * status sinkronisasi tanpa menjatuhkan halaman.
     *
     * @return array{ok:bool,code?:string,message?:string,data?:array}
     */
    public function pushQuietly(string $action, array $payload = []): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'code' => 'not_configured', 'message' => 'GOOGLE_APPS_SCRIPT_EXEC_URL/SECRET kosong.'];
        }

        try {
            return ['ok' => true, 'data' => $this->push($action, $payload)];
        } catch (AppsScriptException $exception) {
            Log::warning('Apps Script push gagal', [
                'action' => $action,
                'code' => $exception->errorCode(),
                'request_id' => $exception->requestId(),
            ]);

            return ['ok' => false, 'code' => $exception->errorCode(), 'message' => $exception->getMessage()];
        } catch (Throwable $throwable) {
            // Secret dan isi payload tidak pernah masuk log (AGENTS.md §2.5).
            Log::error('Apps Script push error tak terduga', ['action' => $action, 'error' => $throwable->getMessage()]);

            return ['ok' => false, 'code' => 'client_error', 'message' => $throwable->getMessage()];
        }
    }

    /**
     * Baca isi tab. Aksi baca memakai read key tanpa HMAC; kalau read key kosong
     * fallback ke HMAC penuh agar tetap berfungsi pada deployment ketat.
     *
     * @return array{rows:array<int,array<string,mixed>>,total?:int}
     */
    public function list(string $tab, array $filters = [], int $page = 1, int $pageSize = 50): array
    {
        $payload = ['tab' => $tab, 'filters' => $filters, 'page' => $page, 'pageSize' => $pageSize];

        $data = $this->readKey !== ''
            ? $this->get('records.list', $payload + ['key' => $this->readKey])
            : $this->push('records.list', $payload);

        return ['rows' => $data['rows'] ?? [], 'total' => $data['total'] ?? count($data['rows'] ?? [])];
    }

    /**
     * Remote config untuk klien (maintenance banner, feature flag).
     */
    public function flags(): array
    {
        return $this->readKey !== ''
            ? ($this->get('config.flags.get')['flags'] ?? [])
            : ($this->push('config.flags.get')['flags'] ?? []);
    }

    /**
     * Kesehatan deployment: URL aktif, secret terpasang, tab tersedia.
     */
    public function health(): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'code' => 'not_configured'];
        }

        try {
            return ['ok' => true] + $this->get('health.ping');
        } catch (Throwable $throwable) {
            return ['ok' => false, 'code' => 'ping_failed', 'message' => $throwable->getMessage()];
        }
    }

    /**
     * Kirim satu permintaan tertulis (HMAC) dan kembalikan `data`.
     *
     * @throws AppsScriptException
     * @throws RuntimeException
     */
    public function push(string $action, array $payload = []): array
    {
        $this->assertConfigured();

        $lastError = null;

        for ($attempt = 1; $attempt <= $this->attempts; $attempt++) {
            // Nonce baru tiap percobaan: lihat catatan kelas di atas.
            $nonce = (string) Str::uuid();
            $timestamp = now()->timestamp;

            $body = [
                'action' => $action,
                'source' => $this->source,
                'timestamp' => $timestamp,
                'nonce' => $nonce,
                'signature' => AppsScriptSignature::sign($timestamp, $nonce, $action, $payload, $this->secret),
                'payload' => (object) AppsScriptSignature::prepare($payload),
            ];

            try {
                $response = Http::timeout($this->timeoutSeconds)
                    ->acceptJson()
                    ->asJson()
                    ->post($this->execUrl, $body);
            } catch (ConnectionException $exception) {
                $lastError = new AppsScriptException(
                    'Koneksi ke Apps Script gagal: '.$exception->getMessage(),
                    'connection_error',
                );

                if ($attempt < $this->attempts) {
                    usleep(250_000 * $attempt);

                    continue;
                }

                throw $lastError;
            }

            return $this->interpret($response, $action);
        }

        throw $lastError ?? new AppsScriptException('Apps Script push gagal.', 'unknown');
    }

    /**
     * Permintaan baca GET tanpa HMAC (butuh read key).
     */
    protected function get(string $action, array $params = []): array
    {
        $this->assertConfigured();

        $response = Http::timeout($this->timeoutSeconds)
            ->acceptJson()
            ->get($this->execUrl, array_merge($params, [
                'action' => $action,
                'source' => $this->source,
            ]));

        return $this->interpret($response, $action);
    }

    /**
     * Apps Script selalu 200; yang dinilai adalah field `ok`.
     *
     * @throws AppsScriptException
     */
    protected function interpret(\Illuminate\Http\Client\Response $response, string $action): array
    {
        $envelope = $response->json() ?: [];

        if (! is_array($envelope) || ! array_key_exists('ok', $envelope)) {
            // Biasanya HTML login Google: deployment-nya bukan "Anyone" atau
            // URL-nya salah (memakai /dev, bukan /exec).
            throw new AppsScriptException(
                'Respons Apps Script bukan envelope JSON (HTTP '.$response->status().'). Periksa deploy Web App = "Anyone" dan URL berakhir /exec.',
                'bad_envelope',
            );
        }

        if ($envelope['ok'] === true) {
            return (array) ($envelope['data'] ?? []);
        }

        $error = (array) ($envelope['error'] ?? []);

        throw new AppsScriptException(
            (string) ($error['message'] ?? 'Apps Script menolak permintaan.'),
            (string) ($error['code'] ?? 'rejected'),
            (string) ($envelope['requestId'] ?? ''),
        );
    }

    /**
     * Terima satu record asosiatif atau daftar record, seragamkan ke `records`.
     */
    protected function payloadFor(array $records, array $options): array
    {
        $isList = array_is_list($records);

        return $options + [
            'records' => $isList ? $records : [$records],
        ];
    }

    protected function assertConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException(
                'Google Apps Script belum dikonfigurasi. Set GOOGLE_APPS_SCRIPT_EXEC_URL dan GOOGLE_APPS_SCRIPT_SECRET di .env.',
            );
        }
    }
}
