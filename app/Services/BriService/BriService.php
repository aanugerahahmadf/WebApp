<?php

namespace App\Services\BriService;

use App\Models\Transaction\Transaction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BriService
{
    public function enabled(): bool
    {
        return config('bri.enabled', false)
            && config('bri.client_id') !== ''
            && config('bri.client_secret') !== '';
    }

    /**
     * OAuth 2.0 Client Credentials token. BRI token berlaku ±2 jam (expires_in=179999),
     * disimpan di cache agar tidak request berulang.
     */
    public function accessToken(): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        return Cache::remember('bri_access_token', now()->addHours(1)->subMinutes(5), function (): ?string {
            $response = Http::asForm()->post(config('bri.token_url').'?grant_type=client_credentials', [
                'client_id' => config('bri.client_id'),
                'client_secret' => config('bri.client_secret'),
            ]);

            if (! $response->successful()) {
                Log::error('[BRI] Token request failed ('.$response->status().'): '.$response->body());

                return null;
            }

            $token = $response->json('access_token');
            if (! $token) {
                Log::error('[BRI] Token response tanpa access_token: '.$response->body());

                return null;
            }

            return $token;
        });
    }

    /**
     * BRI memakai HMAC-SHA512 dengan client_secret sebagai kunci.
     * Payload: path={path}&verb={verb}&token={token}&timestamp={timestamp}&body={body}
     */
    public function signature(string $path, string $verb, string $token, string $timestamp, string $body = ''): string
    {
        $payload = "path={$path}&verb={$verb}&token={$token}&timestamp={$timestamp}&body={$body}";

        return base64_encode(hash_hmac('sha512', $payload, config('bri.client_secret'), true));
    }

    private function iso8601(): string
    {
        return gmdate('Y-m-d\TH:i:s.000\Z');
    }

    /**
     * Penarikan mutasi rekening admin (debit & kredit).
     *
     * @return array<int, array<string, string>>
     */
    public function statement(string $startDate, string $endDate, int $maxRows = 200): array
    {
        $token = $this->accessToken();
        if (! $token) {
            return [];
        }

        $body = json_encode([
            'accountNumber' => config('bri.account_number'),
            'startDate' => $startDate,
            'endDate' => $endDate,
        ], JSON_UNESCAPED_SLASHES);

        $timestamp = $this->iso8601();
        $path = '/v2.0/statement';
        $externalId = 'WO-'.uniqid();

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$token,
            'BRI-Timestamp' => $timestamp,
            'BRI-Signature' => $this->signature($path, 'POST', 'Bearer '.$token, $timestamp, $body),
            'BRI-External-Id' => $externalId,
            'Content-Type' => 'application/json',
        ])->post(config('bri.statement_url'), $body);

        if (! $response->successful()) {
            Log::error('[BRI] Statement request failed ('.$response->status().'): '.$response->body());

            return [];
        }

        $data = $response->json();
        if (($data['responseCode'] ?? '') !== '0000') {
            Log::error('[BRI] Statement response code: '.($data['responseCode'] ?? 'none').' - '.($data['responseDescription'] ?? ''));

            return [];
        }

        return array_slice($data['data'] ?? [], 0, max(1, $maxRows));
    }

    /**
     * Informasi rekening (saldo, nama, status) via Account Inquiry.
     *
     * @return array<string, mixed>|null
     */
    public function accountInquiry(): ?array
    {
        $token = $this->accessToken();
        if (! $token) {
            return null;
        }

        $accountNumber = config('bri.account_number');
        $timestamp = $this->iso8601();
        $path = '/v2/inquiry/'.$accountNumber;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$token,
            'BRI-Timestamp' => $timestamp,
            'BRI-Signature' => $this->signature($path, 'GET', 'Bearer '.$token, $timestamp),
        ])->get(config('bri.inquiry_url').'/'.$accountNumber);

        if (! $response->successful()) {
            Log::error('[BRI] Inquiry request failed ('.$response->status().'): '.$response->body());

            return null;
        }

        $data = $response->json();
        if (($data['responseCode'] ?? '') !== '0100') {
            Log::error('[BRI] Inquiry response code: '.($data['responseCode'] ?? 'none').' - '.($data['responseDescription'] ?? ''));

            return null;
        }

        return $data['Data'] ?? null;
    }

    /* ------------------------------------------------------------------
     | BRI Virtual Account (BRIVA) - SNAP BI
     | -----------------------------------------------------------------*/

    /**
     * Fitur Virtual Account aktif bila SNAP diaktifkan + credentials tersedia.
     * HMAC tidak perlu private key; RSA memerlukan private key.
     */
    public function snapEnabled(): bool
    {
        if (! config('bri.snap_enabled', false) || config('bri.client_id') === '') {
            return false;
        }

        $keyType = config('bri.snap_key_type', 'symmetric');

        // HMAC: hanya perlu client_id + client_secret
        if ($keyType === 'symmetric') {
            return config('bri.client_secret') !== '';
        }

        // RSA: perlu private key
        return $this->snapPrivateKey() !== '';
    }

    private function snapPrivateKey(): string
    {
        $path = config('bri.snap_private_key', '');
        if ($path !== '') {
            // Path relatif di-resolve terhadap root project agar tetap ditemukan
            // meskipun CWD (mis. Nginx php-fpm) bukan direktori project.
            $full = (str_starts_with($path, DIRECTORY_SEPARATOR)
                    || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1)
                ? $path
                : base_path($path);
            if (file_exists($full)) {
                return (string) file_get_contents($full);
            }
        }

        return '';
    }

    /**
     * SNAP signature: HMAC-SHA512 dengan client_secret.
     * stringToSign = METHOD:EndpointUrl:AccessToken:lowercase(hex(sha256(body))):Timestamp
     */
    public function snapSignature(string $method, string $url, string $token, string $timestamp, string $body): string
    {
        $bodyHash = strtolower(hash('sha256', $body));
        $stringToSign = "{$method}:{$url}:{$token}:{$bodyHash}:{$timestamp}";

        return base64_encode(hash_hmac('sha512', $stringToSign, config('bri.client_secret'), true));
    }

    /**
     * SNAP access token. Supports两种 tipe:
     * - Asymmetric (RSA): B2B token via SHA256withRSA(PrivateKey, client_ID|timestamp)
     * - Symmetric (HMAC): B2B token via HMAC-SHA512 signature
     */
    public function snapAccessToken(): ?string
    {
        if (! config('bri.snap_enabled', false)) {
            Log::warning('[BRI] SNAP tidak aktif.');

            return null;
        }

        $keyType = config('bri.snap_key_type', 'symmetric');

        if ($keyType === 'asymmetric') {
            return $this->snapAccessTokenRSA();
        }

        return $this->snapAccessTokenHMAC();
    }

    /**
     * SNAP B2B token via HMAC (Symmetric).
     * Signature: HMAC_SHA512(client_secret, "POST:/snap/v1.0/access-token/b2b::{bodyHash}:{timestamp}")
     */
    private function snapAccessTokenHMAC(): ?string
    {
        return Cache::remember('bri_snap_access_token', now()->addMinutes(14), function (): ?string {
            $timestamp = $this->iso8601();
            $body = json_encode(['grantType' => 'client_credentials'], JSON_UNESCAPED_SLASHES);
            $bodyHash = strtolower(hash('sha256', $body));
            $endpoint = '/snap/v1.0/access-token/b2b';
            // AccessToken kosong (kita sedang request token baru)
            $stringToSign = "POST:{$endpoint}::{$bodyHash}:{$timestamp}";

            $signature = base64_encode(hash_hmac('sha512', $stringToSign, config('bri.client_secret'), true));

            Log::info('[BRI] SNAP HMAC token request', [
                'timestamp' => $timestamp,
                'stringToSign' => $stringToSign,
            ]);

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-TIMESTAMP' => $timestamp,
                'X-SIGNATURE' => $signature,
                'X-CLIENT-KEY' => config('bri.client_id'),
            ])->post(config('bri.snap_token_url'), $body);

            if (! $response->successful()) {
                Log::error('[BRI] SNAP HMAC token request failed ('.$response->status().'): '.$response->body());

                return null;
            }

            $token = $response->json('accessToken');
            if (! $token) {
                Log::error('[BRI] SNAP HMAC response tanpa accessToken: '.$response->body());

                return null;
            }

            Log::info('[BRI] SNAP HMAC token obtained successfully');

            return $token;
        });
    }

    /**
     * SNAP B2B token via RSA (Asymmetric) — memerlukan RSA private key.
     * Public key HARUS terdaftar di portal BRI (Manage Snap Key).
     */
    private function snapAccessTokenRSA(): ?string
    {
        $privateKey = $this->snapPrivateKey();
        if ($privateKey === '') {
            Log::warning('[BRI] RSA private key belum dikonfigurasi.');

            return null;
        }

        return Cache::remember('bri_snap_access_token', now()->addMinutes(14), function () use ($privateKey): ?string {
            $timestamp = $this->iso8601();
            $stringToSign = config('bri.client_id').'|'.$timestamp;

            $signature = '';
            if (! openssl_sign($stringToSign, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
                Log::error('[BRI] Gagal membuat RSA signature untuk B2B token.');

                return null;
            }

            $body = json_encode(['grantType' => 'client_credentials'], JSON_UNESCAPED_SLASHES);
            $sigB64 = base64_encode($signature);

            Log::info('[BRI] SNAP RSA token request', [
                'timestamp' => $timestamp,
                'stringToSign' => $stringToSign,
                'signature_len' => strlen($sigB64),
            ]);

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-TIMESTAMP' => $timestamp,
                'X-SIGNATURE' => $sigB64,
                'X-CLIENT-KEY' => config('bri.client_id'),
            ])->timeout(15)->post(config('bri.snap_token_url'), $body);

            if (! $response->successful()) {
                $bodyJson = json_decode($response->body(), true);
                Log::error('[BRI] SNAP RSA token request failed ('.$response->status().')', [
                    'responseCode' => $bodyJson['responseCode'] ?? 'none',
                    'responseMessage' => $bodyJson['responseMessage'] ?? $response->body(),
                ]);

                return null;
            }

            $token = $response->json('accessToken');
            if (! $token) {
                Log::error('[BRI] SNAP RSA response tanpa accessToken: '.$response->body());

                return null;
            }

            Log::info('[BRI] SNAP RSA token obtained successfully');

            return $token;
        });
    }

    /**
     * Membuat nomor Virtual Account untuk sebuah transaksi via BRI.
     * Nomor VA = partnerServiceId + customerNo (unik per transaksi).
     *
     * @return array{something} provider-agnostic VA info, atau null bila gagal
     */
    public function createVirtualAccount(Transaction $transaction): ?array
    {
        $token = $this->snapAccessToken();
        if (! $token) {
            Log::warning('[BRI] createVirtualAccount batal: tidak ada B2B token untuk transaksi #'.$transaction->id);

            return null;
        }

        $partnerServiceId = config('bri.va_partner_service_id', '8808');
        // customerNo: unik sampai 20 digit, berasal dari id transaksi + acak.
        $customerNo = str_pad((string) $transaction->id, 10, '0', STR_PAD_LEFT).substr((string) time(), -6);
        $virtualAccountNo = $partnerServiceId.$customerNo;
        $expiry = now()->addHours((int) config('bri.va_expiry_hours', 24));

        $body = json_encode([
            'partnerServiceId' => $partnerServiceId,
            'customerNo' => $customerNo,
            'virtualAccountNo' => $virtualAccountNo,
            'virtualAccountName' => mb_substr(config('bri.account_holder', 'ADMIN'), 0, 40),
            'virtualAccountEmail' => $transaction->user?->email,
            'virtualAccountPhone' => $transaction->user?->whatsapp,
            'trxId' => $transaction->reference_number,
            'totalAmount' => [
                'value' => number_format((float) $transaction->total_amount, 2, '.', ''),
                'currency' => 'IDR',
            ],
            'additionalInfo' => [
                'orderNo' => (string) $transaction->order?->order_number,
                'expiredDate' => $expiry->format('Y-m-d\TH:i:s.000P'),
            ],
        ], JSON_UNESCAPED_SLASHES);

        $timestamp = $this->iso8601();
        $path = '/snap/v1.0/transfer-va/create-va';
        $externalId = (string) mt_rand(100000000000, 999999999999);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$token,
                'X-TIMESTAMP' => $timestamp,
                'X-SIGNATURE' => $this->snapSignature('POST', $path, $token, $timestamp, $body),
                'X-PARTNER-ID' => config('bri.client_id'),
                'X-EXTERNAL-ID' => $externalId,
                'CHANNEL-ID' => config('bri.va_channel_id', '00009'),
                'Content-Type' => 'application/json',
            ])->post(config('bri.va_create_url'), $body);

            if (! $response->successful()) {
                Log::error('[BRI] create-va failed ('.$response->status().'): '.$response->body());

                return null;
            }

            $data = $response->json();
            if (($data['responseCode'] ?? '') !== '2009000' && ($data['responseCode'] ?? '') !== '2009700') {
                Log::error('[BRI] create-va response code '.($data['responseCode'] ?? 'none').': '.($data['responseMessage'] ?? ''));

                return null;
            }

            $vaData = $data['virtualAccountData'] ?? [];

            $transaction->update([
                'virtual_account_no' => $vaData['virtualAccountNo'] ?? $virtualAccountNo,
                'virtual_account_expiry' => $vaData['expiredDate'] ?? $expiry,
                'payment_gateway' => 'bri_va',
                'payment_method' => 'Virtual Account BRI',
                'metadata' => array_merge($transaction->metadata ?? [], [
                    'bri_va' => $data,
                ]),
            ]);

            return [
                'virtual_account_no' => $transaction->virtual_account_no,
                'virtual_account_expiry' => $transaction->virtual_account_expiry ? $transaction->virtual_account_expiry->format('Y-m-d\TH:i:sP') : null,
                'account_number' => config('bri.account_number'),
                'account_holder' => config('bri.account_holder'),
            ];
        } catch (\Throwable $e) {
            Log::error('[BRI] create-va exception: '.$e->getMessage(), [
                'transaction_id' => $transaction->id,
                'reference' => $transaction->reference_number,
            ]);

            return null;
        }
    }

    /**
     * Membuat kode QRIS dinamis untuk sebuah transaksi via BRI.
     * QR berlaku untuk 1 transaksi (close payment) dan bisa discan dari
     * aplikasi bank/e-wallet mana pun. Dana masuk ke rekening BRI admin.
     *
     * @return array{qr_content: string, qr_expiry: string}|null
     */
    public function createQris(Transaction $transaction): ?array
    {
        $token = $this->snapAccessToken();
        if (! $token || ! config('bri.qr_enabled', false)) {
            Log::warning('[BRI] createQris batal: tidak ada B2B token / QRIS nonaktif untuk #'.$transaction->id);

            return null;
        }

        if (config('bri.qr_merchant_id', '') === '') {
            Log::warning('[BRI] createQris batal: merchant id kosong untuk #'.$transaction->id);

            return null;
        }

        $expiry = now()->addMinutes((int) config('bri.qr_expiry_minutes', 1440));

        $body = json_encode([
            'partnerReferenceNo' => $transaction->reference_number,
            'amount' => [
                'value' => number_format((float) $transaction->total_amount, 2, '.', ''),
                'currency' => 'IDR',
            ],
            'merchantId' => config('bri.qr_merchant_id'),
            'terminalId' => config('bri.qr_terminal_label', 'WO01'),
            'validityPeriod' => $expiry->format('Y-m-d\TH:i:sP'),
            'additionalInfo' => [
                'postalCode' => config('bri.qr_merchant_postal_code', '10560'),
                'feeType' => '1',
            ],
        ], JSON_UNESCAPED_SLASHES);

        $timestamp = $this->iso8601();
        $path = '/snap/v1.0/qr/qr-mpm-generate';
        $externalId = (string) mt_rand(100000000000, 999999999999);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$token,
                'X-TIMESTAMP' => $timestamp,
                'X-SIGNATURE' => $this->snapSignature('POST', $path, $token, $timestamp, $body),
                'X-PARTNER-ID' => config('bri.client_id'),
                'X-EXTERNAL-ID' => $externalId,
                'CHANNEL-ID' => config('bri.va_channel_id', '00009'),
                'Content-Type' => 'application/json',
            ])->post(config('bri.qr_create_url'), $body);

            if (! $response->successful()) {
                Log::error('[BRI] qr-mpm-generate failed ('.$response->status().'): '.$response->body());

                return null;
            }

            $data = $response->json();
            if (($data['responseCode'] ?? '') !== '2009000' && ($data['responseCode'] ?? '') !== '2009700') {
                Log::error('[BRI] qr-mpm-generate response code '.($data['responseCode'] ?? 'none').': '.($data['responseMessage'] ?? ''));

                return null;
            }

            $qrContent = $data['qrContent'] ?? null;
            if (! $qrContent) {
                Log::error('[BRI] qr-mpm-generate tanpa qrContent: '.$response->body());

                return null;
            }

            $transaction->update([
                'payment_gateway' => 'bri_qris',
                'payment_method' => 'QRIS',
                'metadata' => array_merge($transaction->metadata ?? [], [
                    'bri_qris' => [
                        'qr_content' => $qrContent,
                        'reference_no' => $data['referenceNo'] ?? null,
                        'payload' => $data,
                    ],
                ]),
            ]);

            return [
                'qr_content' => $qrContent,
                'qr_expiry' => $data['additionalInfo']['validityPeriod'] ?? $expiry->format('Y-m-d\TH:i:sP'),
            ];
        } catch (\Throwable $e) {
            Log::error('[BRI] qr-mpm-generate exception: '.$e->getMessage(), [
                'transaction_id' => $transaction->id,
                'reference' => $transaction->reference_number,
            ]);

            return null;
        }
    }

    /**
     * Verifikasi signature X-SIGNATURE yang dikirim BRI pada callback
     * (Payment Notification VA). Signature dibentuk dengan HMAC-SHA512
     * dari client_secret atas body mentah (atau hash body).
     */
    public function verifyVaNotification(string $timestamp, string $signature, string $rawBody): bool
    {
        // 1. HMAC_SHA512(clientSecret, rawBody)
        $candidateRaw = base64_encode(hash_hmac('sha512', $rawBody, config('bri.client_secret'), true));
        if (hash_equals($signature, $candidateRaw)) {
            return true;
        }

        // 2. HMAC_SHA512(clientSecret, lowercase(hex(sha256(body))))
        $bodyHash = strtolower(hash('sha256', $rawBody));
        $candidateHash = base64_encode(hash_hmac('sha512', $bodyHash, config('bri.client_secret'), true));
        if (hash_equals($signature, $candidateHash)) {
            return true;
        }

        Log::warning('[BRI] VA notification signature tidak cocok.');

        return false;
    }
}
