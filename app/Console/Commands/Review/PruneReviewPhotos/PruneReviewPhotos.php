<?php

namespace App\Console\Commands\Review\PruneReviewPhotos;

use App\Services\ReviewPhotoStorage\ReviewPhotoStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Bersihkan file foto review yang tidak lagi dirujuk review mana pun.
 *
 * Review tanpa foto tetap tampil normal, jadi aman dijalankan kapan saja.
 * Default hanya melaporkan (dry-run); tambahkan --apply untuk menghapus.
 *
 *   php artisan reviews:prune-photos
 *   php artisan reviews:prune-photos --apply
 *
 * Catatan: nama file foto berbasis hash isi, jadi satu file bisa dirujuk
 * banyak review. File hanya dihapus bila tidak ada satu pun review yang
 * menunjuknya -- bukan hanya review milik akun seeder.
 */
class PruneReviewPhotos extends Command
{
    protected $signature = 'reviews:prune-photos
                            {--apply : Hapus file-nya (tanpa flag ini hanya laporan)}';

    protected $description = 'Hapus file di storage/app/public/review-photos yang tidak dirujuk reviews.photo / reviews.photos';

    public function handle(): int
    {
        $directory = ReviewPhotoStorage::absoluteDirectory();

        if (! File::isDirectory($directory)) {
            $this->components->info('Folder review-photos tidak ada.');

            return self::SUCCESS;
        }

        $referenced = ReviewPhotoStorage::referencedPaths();

        if ($referenced === []) {
            $this->components->warn('Tidak ada foto review yang dirujuk sama sekali — seluruh isi folder akan dihapus.');
        }

        $orphans = ReviewPhotoStorage::orphanFiles($referenced);
        $bytes = array_sum(array_column($orphans, 'bytes'));

        $this->components->twoColumnDetail('Path dirujuk review', (string) count($referenced));
        $this->components->twoColumnDetail('File di disk', (string) count(File::files($directory)));
        $this->components->twoColumnDetail('Akan dihapus', count($orphans).' file ('.$this->formatBytes($bytes).')');

        if ($orphans === []) {
            $this->components->info('Tidak ada file yatim.');

            return self::SUCCESS;
        }

        if (! $this->option('apply')) {
            $this->components->info('Dry-run. Tambahkan --apply untuk menghapus.');

            return self::SUCCESS;
        }

        $result = ReviewPhotoStorage::pruneOrphans(apply: true);

        $this->components->info("Terhapus: {$result['deleted']} file (".$this->formatBytes($result['bytes']).')');

        if ($result['failed'] > 0) {
            $this->components->warn("Gagal: {$result['failed']} file");
        }

        return self::SUCCESS;
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024, 1).' KB';
        }

        if ($bytes < 1073741824) {
            return round($bytes / 1048576, 1).' MB';
        }

        return round($bytes / 1073741824, 2).' GB';
    }
}