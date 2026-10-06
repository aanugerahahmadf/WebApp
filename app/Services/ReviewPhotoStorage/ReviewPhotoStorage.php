<?php

namespace App\Services\ReviewPhotoStorage;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Utilitas bersama untuk file foto review di storage/app/public/review-photos.
 *
 * Dua aturan yang dijaga di sini:
 *
 *  1. Penamaan file berbasis isi (content-addressed). Foto yang sama hanya
 *     disimpan sekali, jadi menjalankan seeder berkali-kali tidak menambah
 *     salinan. Sebelumnya nama file diacak dengan Str::random(), sehingga
 *     setiap run menyalin ulang gambar identik dengan nama baru dan file lama
 *     tidak pernah tertimpa -- satu kali siklus seeder sudah menghasilkan
 *     ratusan ribu file.
 *
 *  2. Penghapusan harus sadar-berbagian. Karena nama file diturunkan dari
 *     hash isi, satu file bisa dirujuk banyak review. File hanya boleh
 *     dihapus bila tidak ada satu pun review yang menunjuknya.
 */
class ReviewPhotoStorage
{
    /**
     * Direktori photos relatif terhadap root disk `public`.
     */
    public const DIRECTORY = 'review-photos';

    /**
     * Panjang hash yang dipakai pada nama file.
     */
    protected const HASH_LENGTH = 32;

    /**
     * Path absolut direktori photos.
     */
    public static function absoluteDirectory(): string
    {
        return storage_path('app/public/'.self::DIRECTORY);
    }

    /**
     * Bangun nama file deterministik dari isi file sumber.
     *
     * Ekstensi ikut dipertahankan supaya reviewer/mIME di web tetap benar.
     */
    public static function nameFor(string $sourcePath): string
    {
        $ext = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION)) ?: 'jpg';

        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $ext = 'jpg';
        }

        // Hash isi jadi acuan dedup: foto yang sama tidak pernah tersalin dua
        // kali. Tapi `hash_file()` bisa gagal walaupun file-nya ada -- di
        // Windows `is_readable()` bisa lulus lalu open() ditolak kalau proses
        // lain sedang memegang file (antivirus, editor, runner paralel).
        // Warning-nya jadi ErrorException dan menjatuhkan seluruh test run,
        // padahal niat kode ini cuma "lewati foto ini".
        //
        // Karena itu dibungkus, dengan fallback ke path + ukuran + waktu
        // modifikasi. Fallback itu tetap deterministik untuk file yang sama,
        // jadi sifat idempoten dan bebas-salin-ganda tidak hilang.
        $hash = @hash_file('sha256', $sourcePath);

        if ($hash === false) {
            clearstatcache(true, $sourcePath);

            $hash = hash('sha256', implode('|', [
                $sourcePath,
                (string) (@filesize($sourcePath) ?: 0),
                (string) (@filemtime($sourcePath) ?: 0),
            ]));
        }

        return 'review-'.substr($hash, 0, self::HASH_LENGTH).'.'.$ext;
    }

    /**
     * Salin $source ke review-photos bila belum ada, dan kembalikan path relatifnya.
     *
     * Idempoten: pemanggilan berulang untuk file sumber yang sama menghasilkan
     * path yang sama dan tidak menulis ulang bytes di disk.
     */
    public static function store(string $sourcePath): ?string
    {
        if (! is_file($sourcePath) || ! is_readable($sourcePath)) {
            return null;
        }

        $directory = self::absoluteDirectory();
        File::ensureDirectoryExists($directory);

        $name = self::nameFor($sourcePath);
        $destination = $directory.'/'.$name;

        if (! File::exists($destination)) {
            File::copy($sourcePath, $destination);
        }

        return self::DIRECTORY.'/'.$name;
    }

    /**
     * Kumpulkan seluruh path foto yang masih dirujuk review mana pun.
     *
     * Hanya path relatif di dalam review-photos/ yang dianggap sah, supaya
     * data rusak di DB tidak membuat prune ikut menyentuh file lain.
     *
     * @return array<string, true>
     */
    public static function referencedPaths(): array
    {
        $paths = [];

        DB::table('reviews')
            ->select('photo', 'photos')
            ->orderBy('id')
            ->chunk(500, function ($rows) use (&$paths): void {
                foreach ($rows as $row) {
                    if (is_string($row->photo) && $row->photo !== '') {
                        if (self::isManagedPath($row->photo)) {
                            $paths[$row->photo] = true;
                        }
                    }

                    if (! is_string($row->photos) || $row->photos === '') {
                        continue;
                    }

                    $decoded = json_decode($row->photos, true);

                    if (! is_array($decoded)) {
                        continue;
                    }

                    foreach ($decoded as $path) {
                        if (is_string($path) && $path !== '') {
                            if (self::isManagedPath($path)) {
                                $paths[$path] = true;
                            }
                        }
                    }
                }
            });

        return $paths;
    }

    /**
     * Daftar file yatim di direktori photos: ada di disk tapi tidak dirujuk review.
     *
     * @return array<int, array{name: string, path: string, bytes: int}>
     */
    public static function orphanFiles(?array $referenced = null): array
    {
        $directory = self::absoluteDirectory();

        if (! File::isDirectory($directory)) {
            return [];
        }

        $referenced ??= self::referencedPaths();
        $orphans = [];

        foreach (File::files($directory) as $file) {
            $relative = self::DIRECTORY.'/'.$file->getFilename();

            if (isset($referenced[$relative])) {
                continue;
            }

            $orphans[] = [
                'name' => $file->getFilename(),
                'path' => $file->getPathname(),
                'bytes' => (int) $file->getSize(),
            ];
        }

        return $orphans;
    }

    /**
     * Hapus file yatim. Default hanya melaporkan; $apply=true baru menghapus.
     *
     * Aman untuk file yang dipakai bersama: sebuah file hanya dihapus bila
     * tidak dirujuk review mana pun saat pemanggilan.
     *
     * @return array{deleted: int, failed: int, bytes: int}
     */
    public static function pruneOrphans(bool $apply = false): array
    {
        $orphans = self::orphanFiles();

        $deleted = 0;
        $failed = 0;
        $bytes = 0;

        foreach ($orphans as $orphan) {
            $bytes += $orphan['bytes'];

            if ($apply && File::delete($orphan['path'])) {
                $deleted++;
            } elseif ($apply) {
                $failed++;
            }
        }

        return ['deleted' => $deleted, 'failed' => $failed, 'bytes' => $bytes];
    }

    /**
     * Path relatif ini milik kita (review-photos/...) dan aman diproses?
     *
     * Menolak path absolut, `..`, dan direktori lain supaya prune tidak
     * mungkin keluar dari review-photos.
     */
    protected static function isManagedPath(string $path): bool
    {
        $normalized = str_replace('\\', '/', $path);

        if (str_contains($normalized, '..') || str_starts_with($normalized, '/')) {
            return false;
        }

        return str_starts_with($normalized, self::DIRECTORY.'/');
    }
}
