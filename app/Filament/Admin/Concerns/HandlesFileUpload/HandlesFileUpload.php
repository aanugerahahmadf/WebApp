<?php

namespace App\Filament\Admin\Concerns\HandlesFileUpload;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Membaca berkas yang diunggah lewat form Filament.
 *
 * Bentuk state-nya berbeda-beda tergantung apakah berkasnya sudah
 * dipindahkan ke disk atau belum:
 *
 *   - objek `UploadedFile` (saat pengujian, atau saat form baru diisi),
 *   - path sementara di disk,
 *   - path relatif pada disk tujuan (`local`).
 *
 * Kalau hanya satu bentuk yang ditangani, berkas yang sudah dipindahkan akan
 * ditulis sebagai berkas KOSONG -- dan itu tidak kelihatan dari luar:
 * berkasnya ada, hanya isinya hilang.
 */
trait HandlesFileUpload
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{name: string, contents: string}
     */
    protected function resolveUploadedFile(array $data, string $field = 'uploaded_file'): array
    {
        $value = $data[$field] ?? null;

        $file = is_array($value) ? ($value[0] ?? null) : $value;

        $fallback = 'uploaded_'.now()->format('Ymd_His').'.php';

        if ($file instanceof UploadedFile) {
            $path = $file->getRealPath();

            return [
                'name' => $file->getClientOriginalName() ?: $fallback,
                'contents' => $path !== false && is_file($path) ? (string) file_get_contents($path) : '',
            ];
        }

        if (is_string($file) && $file !== '') {
            if (is_file($file)) {
                return [
                    'name' => basename($file),
                    'contents' => (string) file_get_contents($file),
                ];
            }

            if (Storage::disk('local')->exists($file)) {
                return [
                    'name' => basename($file),
                    'contents' => (string) Storage::disk('local')->get($file),
                ];
            }

            return ['name' => basename($file), 'contents' => ''];
        }

        return ['name' => $fallback, 'contents' => ''];
    }
}
