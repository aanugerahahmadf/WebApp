<?php

namespace App\Services\GoogleSheets;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client tipis Google Sheets API v4 untuk sheet review.
 *
 * Auth pakai service account (JWT) lewat paket `google/auth` yang sudah ada
 * sebagai dependency google/cloud-firestore -- jadi tidak perlu menambah
 * dependency baru.
 *
 * Penting: Google Sheets hanya bisa menyimpan teks/angka. Foto tidak bisa
 * ikut diunggah ke sini; yang ditulis ke sheet hanyalah path dan URL-nya.
 */
class GoogleSheetsClient
{
    /**
     * Scope minimum untuk baca-tulis spreadsheet milik sendiri.
     */
    public const SCOPE = 'https://www.googleapis.com/auth/spreadsheets';

    protected ?string $accessToken = null;

    public function __construct(
        protected string $spreadsheetId,
        protected string $serviceAccountJson,
    ) {}

    /**
     * Apakah konfigurasi cukup untuk talked.
     */
    public function isConfigured(): bool
    {
        return $this->spreadsheetId !== '' && $this->serviceAccountJson !== '';
    }

    /**
     * Tulis baris ke sebuah range, menimpa isi range tersebut.
     *
     * Sheets API membatasi 2.000.000 sel per request, jadi pemanggil wajib
     * memecah data menjadi beberapa chunk.
     *
     * @param  array<int, array<int, string|int|float|null>>  $rows
     */
    public function write(string $range, array $rows): int
    {
        $this->assertConfigured();

        $response = Http::withToken($this->token())
            ->timeout(120)
            ->put($this->url($range), [
                'valueRange' => [
                    'range' => $range,
                    'majorDimension' => 'ROWS',
                    'values' => $rows,
                ],
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('Google Sheets write gagal (HTTP '.$response->status().'): '.$response->body());
        }

        return (int) $response->json('updates.updatedRows', count($rows));
    }

    /**
     * Ambil nilai sebuah range.
     *
     * @return array<int, array<int, mixed>>
     */
    public function read(string $range): array
    {
        $this->assertConfigured();

        $response = Http::withToken($this->token())
            ->timeout(60)
            ->get($this->url($range));

        if ($response->failed()) {
            throw new \RuntimeException('Google Sheets read gagal (HTTP '.$response->status().'): '.$response->body());
        }

        return $response->json('values', []) ?? [];
    }

    /**
     * Berapa baris yang sudah dipakai di tab ini, untuk menentukan titik tulis.
     */
    public function usedRowCount(string $range): int
    {
        return count($this->read($range));
    }

    protected function url(string $range): string
    {
        return sprintf(
            'https://sheets.googleapis.com/v4/spreadsheets/%s/values/%s',
            rawurlencode($this->spreadsheetId),
            $this->encodeRange($range),
        );
    }

    /**
     * Slash/spasi pada range harus di-URL-encode, tapi nama tab dengan spasi
     * perlu dibungkus tanda kutip.
     */
    protected function encodeRange(string $range): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $range)));
    }

    /**
     * Ambil access token dari service account, di-cache selama proses ini.
     */
    protected function token(): string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        $key = json_decode($this->serviceAccountJson, true);

        if (! is_array($key) || ! isset($key['client_email'], $key['private_key'])) {
            throw new \RuntimeException('GOOGLE_SHEETS_SERVICE_ACCOUNT_JSON bukan JSON service account yang valid (butuh client_email + private_key).');
        }

        try {
            $credentials = new ServiceAccountCredentials(self::SCOPE, $key);
            $token = $credentials->fetchAuthToken();
        } catch (\Throwable $e) {
            Log::error('Google Sheets auth gagal', ['error' => $e->getMessage()]);

            throw new \RuntimeException('Gagal autentikasi service account: '.$e->getMessage(), 0, $e);
        }

        $accessToken = $token['access_token'] ?? null;

        if (! is_string($accessToken) || $accessToken === '') {
            throw new \RuntimeException('Token akses tidak dikembalikan Google.');
        }

        return $this->accessToken = $accessToken;
    }

    protected function assertConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('Google Sheets belum dikonfigurasi. Set GOOGLE_SHEETS_SPREADSHEET_ID dan GOOGLE_SHEETS_SERVICE_ACCOUNT_JSON di .env.');
        }
    }
}