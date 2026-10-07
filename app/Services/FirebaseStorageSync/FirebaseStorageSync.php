<?php

namespace App\Services\FirebaseStorageSync;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Sync file upload lokal ke Firebase Storage (Google Cloud Storage).
 *
 * Cara pakai:
 *   1. Install driver: composer require superbalist/laravel-google-cloud-storage
 *   2. Set FIREBASE_CREDENTIALS di .env (path ke service-account.json)
 *   3. Set FIREBASE_STORAGE_BUCKET di .env
 *   4. Jalankan: php artisan firebase:sync --apply
 *   5. Otomatis via scheduler (lihat Kernel.php)
 *
 * File yang di-sync: storage/app/public/{Packages,Products,review-photos}/
 * Tujuan: Firebase bucket/uploads/{Packages,Products,review-photos}/
 */
class FirebaseStorageSync
{
    protected string $localDisk = 'public';
    protected string $remoteDisk = 'firebase';
    protected array $folders = ['Packages', 'Products', 'review-photos'];

    public function sync(bool $apply = false, bool $deleteLocalAfterSync = false): array
    {
        if (! $this->isConfigured()) {
            return ['error' => 'Firebase Storage belum dikonfigurasi. Set FIREBASE_CREDENTIALS & FIREBASE_STORAGE_BUCKET di .env'];
        }

        $results = [
            'synced' => 0,
            'skipped' => 0,
            'failed' => 0,
            'bytes_synced' => 0,
            'bytes_freed_local' => 0,
            'details' => [],
        ];

        foreach ($this->folders as $folder) {
            $result = $this->syncFolder($folder, $apply, $deleteLocalAfterSync);
            $results['synced'] += $result['synced'];
            $results['skipped'] += $result['skipped'];
            $results['failed'] += $result['failed'];
            $results['bytes_synced'] += $result['bytes_synced'];
            $results['bytes_freed_local'] += $result['bytes_freed_local'];
            $results['details'][$folder] = $result;
        }

        return $results;
    }

    protected function syncFolder(string $folder, bool $apply, bool $deleteLocal): array
    {
        $localRoot = Storage::disk($this->localDisk)->path($folder);

        if (! File::isDirectory($localRoot)) {
            return ['synced' => 0, 'skipped' => 0, 'failed' => 0, 'bytes_synced' => 0, 'bytes_freed_local' => 0];
        }

        $files = File::files($localRoot);
        $result = ['synced' => 0, 'skipped' => 0, 'failed' => 0, 'bytes_synced' => 0, 'bytes_freed_local' => 0];

        foreach ($files as $file) {
            $filename = $file->getFilename();
            $relativePath = $folder.'/'.$filename;
            $remotePath = 'uploads/'.$relativePath;

            try {
                // Cek apakah sudah ada di Firebase (by size + name)
                if ($this->existsOnRemote($remotePath, $file->getSize())) {
                    $result['skipped']++;
                    if ($apply && $deleteLocal) {
                        $size = $file->getSize();
                        File::delete($file->getPathname());
                        $result['bytes_freed_local'] += $size;
                    }
                    continue;
                }

                if (! $apply) {
                    $result['skipped']++; // dry-run counts as skipped
                    continue;
                }

                // Upload ke Firebase
                $contents = File::get($file->getPathname());
                Storage::disk($this->remoteDisk)->put($remotePath, $contents, 'public');

                $size = $file->getSize();
                $result['synced']++;
                $result['bytes_synced'] += $size;

                // Hapus lokal jika diminta
                if ($deleteLocal) {
                    File::delete($file->getPathname());
                    $result['bytes_freed_local'] += $size;
                }

                Log::info("Firebase sync: {$relativePath} -> {$remotePath} ({$this->formatBytes($size)})");
            } catch (\Throwable $e) {
                $result['failed']++;
                Log::error("Firebase sync gagal: {$relativePath} - ".$e->getMessage());
            }
        }

        return $result;
    }

    protected function existsOnRemote(string $remotePath, int $localSize): bool
    {
        try {
            if (! Storage::disk($this->remoteDisk)->exists($remotePath)) {
                return false;
            }

            // Bandingkan size untuk memastikan file sama
            $remoteSize = Storage::disk($this->remoteDisk)->size($remotePath);

            return $remoteSize === $localSize;
        } catch (\Throwable) {
            return false;
        }
    }

    public function isConfigured(): bool
    {
        $config = config('filesystems.disks.firebase');

        return ! empty($config['project_id'])
            && ! empty($config['bucket'])
            && ! empty($config['key_file'])
            && File::exists($config['key_file']);
    }

    public function getRemoteUrl(string $relativePath): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $remotePath = 'uploads/'.$relativePath;

        if (! Storage::disk($this->remoteDisk)->exists($remotePath)) {
            return null;
        }

        return Storage::disk($this->remoteDisk)->url($remotePath);
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) return $bytes.' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1).' KB';
        if ($bytes < 1073741824) return round($bytes / 1048576, 1).' MB';
        return round($bytes / 1073741824, 2).' GB';
    }
}