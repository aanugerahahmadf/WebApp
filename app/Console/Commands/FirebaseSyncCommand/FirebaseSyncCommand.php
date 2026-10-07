<?php

namespace App\Console\Commands\FirebaseSyncCommand;

use App\Services\FirebaseStorageSync\FirebaseStorageSync;
use Illuminate\Console\Command;

/**
 * Sync file upload lokal (storage/app/public) ke Firebase Storage (GCS).
 *
 * Tujuan: Pindahkan file besar (Packages, Products, review-photos) ke cloud
 * supaya storage lokal tidak penuh. Firebase gratis 5 GB, auto-scaling.
 *
 *   php artisan firebase:sync              # dry-run (laporan saja)
 *   php artisan firebase:sync --apply      # upload ke Firebase
 *   php artisan firebase:sync --apply --delete-local  # upload + hapus lokal
 */
class FirebaseSyncCommand extends Command
{
    protected $signature = 'firebase:sync
                            {--apply : Benar-benar upload (tanpa flag ini hanya dry-run)}
                            {--delete-local : Hapus file lokal setelah upload berhasil}
                            {--folders=* : Folder spesifik (default: Packages,Products,review-photos)}';

    protected $description = 'Sync upload lokal ke Firebase Storage (Google Cloud Storage)';

    public function handle(FirebaseStorageSync $sync): int
    {
        $apply = (bool) $this->option('apply');
        $deleteLocal = (bool) $this->option('delete-local');

        if (! $apply && $deleteLocal) {
            $this->error('--delete-local hanya bisa dipakai bersama --apply');

            return self::INVALID;
        }

        if (! $sync->isConfigured()) {
            $this->components->error('Firebase Storage belum dikonfigurasi.');
            $this->components->twoColumnDetail('Butuh', 'FIREBASE_CREDENTIALS & FIREBASE_STORAGE_BUCKET di .env');
            $this->components->twoColumnDetail('Service account', 'storage/keys/firebase-service-account.json (gitignored)');
            $this->components->twoColumnDetail('Driver', 'composer require superbalist/laravel-google-cloud-storage');

            return self::FAILURE;
        }

        $this->components->info($apply ? 'Mode: UPLOAD' : 'Mode: DRY-RUN (tambahkan --apply untuk upload)');
        if ($deleteLocal) {
            $this->components->warn('File lokal AKAN DIHAPUS setelah upload berhasil.');
        }

        $folders = $this->option('folders');
        if ($folders) {
            // Override folders via closure hack - create new instance with custom folders
            $reflection = new \ReflectionClass($sync);
            $property = $reflection->getProperty('folders');
            $property->setAccessible(true);
            $property->setValue($sync, $folders);
        }

        $result = $sync->sync($apply, $deleteLocal);

        if (isset($result['error'])) {
            $this->components->error($result['error']);

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->twoColumnDetail('Folder diproses', count($result['details']));

        foreach ($result['details'] as $folder => $detail) {
            $this->components->twoColumnDetail(
                $folder,
                "synced: {$detail['synced']}, skipped: {$detail['skipped']}, failed: {$detail['failed']}"
            );
        }

        $this->newLine();
        $this->components->twoColumnDetail('Total synced', $result['synced'].' file ('.$this->formatBytes($result['bytes_synced']).')');
        $this->components->twoColumnDetail('Total skipped', $result['skipped']);
        $this->components->twoColumnDetail('Total failed', $result['failed']);

        if ($deleteLocal) {
            $this->components->twoColumnDetail('Ruang lokal dibebaskan', $this->formatBytes($result['bytes_freed_local']));
        }

        if (! $apply) {
            $this->components->info('Dry-run selesai. Jalankan dengan --apply untuk benar-benar upload.');
        }

        return self::SUCCESS;
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) return $bytes.' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1).' KB';
        if ($bytes < 1073741824) return round($bytes / 1048576, 1).' MB';
        return round($bytes / 1073741824, 2).' GB';
    }
}