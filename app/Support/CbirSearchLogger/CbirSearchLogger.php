<?php

namespace App\Support\CbirSearchLogger;

use App\Models\Cbir\CbirSearch;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Pencatat riwayat pencarian visual (tabel `cbir_searches` +
 * `cbir_search_results`).
 *
 * Tabel itu sudah ada sejak awal, tapi **tidak pernah diisi**: sampai
 * 2026-10-28 isinya 0 baris walau pencarian visual sudah dipakai, sehingga
 * panel admin tidak punya bukti apa pun bahwa fitur CBIR bekerja, dan angka
 * "berapa kali dicari / seberapa mirip hasilnya" tidak bisa dilaporkan.
 *
 * Tiga pintu pencarian visual mencatat lewat kelas ini:
 *   - halaman `/cbir-search` (panel Welcome)  -> CbirSearchPage::runCbirSearch()
 *   - `POST /api/cbir/search`                 -> CBIRController::searchSimilar()
 *   - `POST /api/search/image` (aplikasi HP)  -> SearchController::byImage()
 *
 * Aturan penting: pencatatan **tidak boleh menggagalkan pencarian**. Kalau
 * penyimpanan gagal (disk penuh, kolom berubah, dsb.), pengguna tetap menerima
 * hasilnya; kegagalannya hanya masuk log.
 */
final class CbirSearchLogger
{
    /** Batas baris hasil yang disimpan per pencarian (top-N). */
    public const MAX_RESULTS = 10;

    /**
     * Catat satu pencarian visual.
     *
     * @param  int|null  $userId  pemilik pencarian; `null` = tamu (tidak dicatat,
     *                            karena kolom `user_id` wajib terisi)
     * @param  string|null  $queryImageUrl  path/URL foto kueri
     * @param  iterable  $results  hasil AI Core maupun hasil yang sudah diperkaya
     *                             (`type`, `similarity`, `score`, `owner_id`/`data.id`)
     */
    public static function record(?int $userId, ?string $queryImageUrl, iterable $results): ?CbirSearch
    {
        if (! $userId) {
            return null;
        }

        try {
            $rows = self::normalize($results);

            $search = CbirSearch::create([
                'user_id' => $userId,
                'query_image_url' => (string) ($queryImageUrl ?? '-'),
                'results_count' => count($rows),
                'top_similarity' => $rows === [] ? 0.0 : max(array_column($rows, 'similarity')),
            ]);

            foreach (array_slice($rows, 0, self::MAX_RESULTS) as $row) {
                $search->results()->create($row);
            }

            return $search;
        } catch (\Throwable $e) {
            // Pencariannya sudah berhasil; riwayat yang gagal dicatat bukan
            // alasan untuk menampilkan error ke pengguna.
            Log::warning('CBIR: gagal mencatat riwayat pencarian', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Simpan foto kueri lalu catat pencariannya.
     *
     * Foto kueri ikut disimpan supaya admin bisa melihat APA yang dicari
     * (kolom `query_image_url` memang disediakan untuk itu). Kalau
     * penyimpanannya gagal, pencarian tetap dicatat tanpa foto.
     */
    public static function recordUpload(?int $userId, ?UploadedFile $file, iterable $results): ?CbirSearch
    {
        $path = null;

        if ($file) {
            try {
                $path = $file->store('cbir-queries', 'public') ?: null;
            } catch (\Throwable $e) {
                Log::warning('CBIR: gagal menyimpan foto kueri', ['error' => $e->getMessage()]);
            }
        }

        return self::record($userId, $path, $results);
    }

    /**
     * Samakan bentuk hasil dari tiga pintu menjadi baris `cbir_search_results`.
     *
     * `similarity` di aplikasi ini berupa PERSEN (0..100) karena AI Core
     * mengirim persen; kalau ada pemanggil yang mengirim 0..1 nilainya
     * dinaikkan supaya satu kolom tidak bercampur dua skala.
     *
     * @return array<int, array{type: string, item_id: int, similarity: float, score: float}>
     */
    private static function normalize(iterable $results): array
    {
        if ($results instanceof Arrayable) {
            $results = $results->toArray();
        }

        $rows = [];

        foreach ($results as $result) {
            if (! is_array($result)) {
                continue;
            }

            $type = (string) ($result['type'] ?? 'catalog');

            $itemId = $result['owner_id']
                ?? $result['data']['id']
                ?? $result['item_id']
                ?? null;

            if (! $itemId) {
                continue;
            }

            $similarity = (float) ($result['similarity'] ?? $result['score'] ?? 0);

            if ($similarity > 0.0 && $similarity <= 1.0) {
                $similarity *= 100;
            }

            $rows[] = [
                'type' => $type,
                'item_id' => (int) $itemId,
                'similarity' => round(max(0.0, min(100.0, $similarity)), 2),
                'score' => round((float) ($result['score'] ?? 0), 6),
            ];
        }

        return $rows;
    }

    /**
     * URL yang bisa ditampilkan untuk foto kueri (relatif -> absolut).
     */
    public static function queryImageUrl(?string $path): ?string
    {
        if (! $path || $path === '-') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return Storage::disk('public')->exists($path)
            ? Storage::disk('public')->url($path)
            : null;
    }
}
