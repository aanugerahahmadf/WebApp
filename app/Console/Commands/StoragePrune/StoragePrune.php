<?php

namespace App\Console\Commands\StoragePrune;

use App\Services\ReviewPhotoStorage\ReviewPhotoStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Pangkas sampah di storage/ supaya folder tidak terus tumbuh sampai disk penuh.
 *
 * Yang ditangani:
 *   - log lama (rotasi harian + sisa channel `single` yang dulu tak pernah dibersihkan)
 *   - log server-service.* yang dirotasi manual oleh proses serve
 *   - file sf_proc_* / serve.* di storage/framework/temp (sisa subprocess yang menggantung)
 *   - file foto review yatim (opsional, --photos)
 *
 * File yang baru saja disentuh dilewati supaya tidak berebut handle dengan
 * proses yang sedang berjalan.
 *
 *   php artisan app:prune-storage --dry-run
 *   php artisan app:prune-storage
 *   php artisan app:prune-storage --photos --apply
 */
class StoragePrune extends Command
{
    protected $signature = 'app:prune-storage
                            {--days=7 : Umur minimum file log sebelum dihapus}
                            {--photos : Sekalian pangkas foto review yatim}
                            {--apply : Benar-benar hapus (tanpa ini hanya laporan)}
                            {--dry-run : Alias eksplisit untuk laporan saja}';

    protected $description = 'Pangkas log lama, file temp yatim, dan opsional foto review yatim di storage/';

    /**
     * Pola nama file log yang boleh dihapus.
     *
     * @var array<int, string>
     */
    protected array $logPatterns = [
        'laravel-*.log',
        'server-service.*.log',
        'serve.log',
        'serve_error.log',
        'browser.log',
        '*.log.*',
    ];

    public function handle(): int
    {
        $days = max(0, (int) $this->option('days'));
        $apply = (bool) $this->option('apply') && ! $this->option('dry-run');
        $cutoff = now()->subDays($days);

        $this->components->twoColumnDetail('Mode', $apply ? 'HAPUS' : 'laporan (dry-run)');
        $this->components->twoColumnDetail('Ambang umur', $days.' hari');

        $freed = 0;
        $freed += $this->pruneLogs($cutoff, $apply);
        $freed += $this->pruneLegacySingleLog($apply);
        $freed += $this->pruneTempFiles($cutoff, $apply);
        $freed += $this->pruneMediaLibraryTemp($cutoff, $apply);

        if ($this->option('photos')) {
            $freed += $this->prunePhotos($apply);
        } else {
            $this->components->twoColumnDetail('Foto review yatim', 'lewati (--photos)');
        }

        $this->newLine();
        $this->components->info($apply
            ? 'Selesai. Total ruang dibebaskan: '.$this->formatBytes($freed)
            : 'Dry-run. Tambahkan --apply untuk menghapus.');

        return self::SUCCESS;
    }

    /**
     * Hapus file log yang sudah tua dan tidak sedang ditulis.
     */
    protected function pruneLogs($cutoff, bool $apply): int
    {
        $directory = storage_path('logs');
        $freed = 0;
        $deleted = 0;

        foreach ($this->logPatterns as $pattern) {
            foreach (File::glob($directory.'/'.$pattern) as $path) {
                $file = new \SplFileInfo($path);

                // Jangan sentuh file yang baru saja ditulis; bisa jadi log hari ini.
                if ($file->getMTime() > $cutoff->getTimestamp()) {
                    continue;
                }

                $size = (int) $file->getSize();

                if ($apply && File::delete($path)) {
                    $freed += $size;
                    $deleted++;
                }
            }
        }

        $this->components->twoColumnDetail(
            'Log lama',
            $apply && $freed > 0
                ? "{$deleted} file / ".$this->formatBytes($freed)
                : 'tidak ada'
        );

        return $freed;
    }

    /**
     * Hapus `laravel.log` warisan dari channel `single`.
     *
     * File ini dulu tidak pernah dirotasi dan pernah membengkak jadi 63 MB.
     * Aman dihapus tanpa syarat umur begitu channel `daily` aktif: kalau file
     * laravel-YYYY-MM-DD.log sudah ada, tidak ada proses yang menulis ke
     * laravel.log lagi.
     *
     * Catatan Windows: selama proses web masih memegang handle ke laravel.log,
     * unlink gagal. Ruang baru benar-benar bebas setelah web server restart,
     * jadi ukuran yang dikembalikan di sini hanya yang benar-benar terhapus.
     */
    protected function pruneLegacySingleLog(bool $apply): int
    {
        $directory = storage_path('logs');

        // Pakai glob, bukan file_exists: di Windows file_exists() mengembalikan
        // false untuk file yang dikunci proses lain, padahal entrinya masih
        // ada di direktori. Kalau pakai file_exists, log 63 MB ini akan
        // diam-diam terlewat.
        $legacy = File::glob($directory.'/laravel.log')[0] ?? null;

        if ($legacy === null || File::glob($directory.'/laravel-*.log') === []) {
            return 0;
        }

        $size = is_file($legacy) ? (int) File::size($legacy) : 0;
        $freed = 0;

        if (! $apply) {
            $this->components->twoColumnDetail('Log single warisan', 'laravel.log / '.$this->formatBytes($size));

            return 0;
        }

        if (@unlink($legacy)) {
            $freed = $size;
            $this->components->twoColumnDetail('Log single warisan', 'laravel.log / '.$this->formatBytes($size));
        } else {
            // Proses web masih memegang handle ke file ini.
            $this->components->twoColumnDetail('Log single warisan', 'terkunci / '.$this->formatBytes($size).' — restart web server, lalu jalankan lagi');
        }

        return $freed;
    }

    /**
     * Hapus sisa file temp subprocess yang menggantung (sf_proc_*, serve.*).
     */
    protected function pruneTempFiles($cutoff, bool $apply): int
    {
        $directory = storage_path('framework/temp');
        $freed = 0;
        $deleted = 0;

        if (! File::isDirectory($directory)) {
            $this->components->twoColumnDetail('Temp subprocess', 'folder tidak ada');

            return 0;
        }

        foreach (File::files($directory) as $file) {
            if ($file->getFilename() === '.gitignore') {
                continue;
            }

            if (! preg_match('/^(sf_proc_|serve\.)/', $file->getFilename())) {
                continue;
            }

            if ($file->getMTime() > $cutoff->getTimestamp()) {
                continue;
            }

            $size = (int) $file->getSize();

            if ($apply && File::delete($file->getPathname())) {
                $freed += $size;
                $deleted++;
            }
        }

        $this->components->twoColumnDetail(
            'Temp subprocess',
            $freed > 0 ? "{$deleted} file / ".$this->formatBytes($freed) : 'tidak ada'
        );

        return $freed;
    }

    /**
     * Hapus file temp Media Library (storage/media-library/temp).
     *
     * Spatie Media Library menyimpan file upload sementara di sini saat
     * conversion/responsive image generation berjalan. Jika upload gagal,
     * dibatalkan, atau proses worker mati, folder sub-direktori (UUID acak)
     * beserta isinya menjadi yatim dan tidak pernah dibersihkan otomatis.
     *
     * Kita hapus seluruh subtree yang umurnya > $cutoff.
     */
    protected function pruneMediaLibraryTemp($cutoff, bool $apply): int
    {
        $directory = storage_path('media-library/temp');
        $freed = 0;
        $deletedDirs = 0;

        if (! File::isDirectory($directory)) {
            $this->components->twoColumnDetail('MediaLibrary temp', 'folder tidak ada');

            return 0;
        }

        $dirs = File::directories($directory);

        // Iterasi subdirektori (setiap upload bikin folder UUID sendiri)
        foreach ($dirs as $dir) {
            $dirInfo = new \SplFileInfo($dir);
            $mtime = $dirInfo->getMTime();

            // Lewati folder yang baru saja disentuh (mungkin upload sedang berjalan)
            if ($mtime > $cutoff->getTimestamp()) {
                continue;
            }

            // Hitung total size folder
            $dirSize = 0;
            foreach (File::allFiles($dir) as $file) {
                $dirSize += (int) $file->getSize();
            }

            if ($apply) {
                File::deleteDirectory($dir);
                $freed += $dirSize;
                $deletedDirs++;
            } else {
                // Dry-run: akumulasi ukuran yang akan dibebaskan
                $freed += $dirSize;
                $deletedDirs++;
            }
        }

        $this->components->twoColumnDetail(
            'MediaLibrary temp',
            $freed > 0 ? "{$deletedDirs} folder / ".$this->formatBytes($freed) : 'tidak ada'
        );

        return $freed;
    }

    /**
     * Pangkas foto review yang tidak dirujuk review mana pun.
     */
    protected function prunePhotos(bool $apply): int
    {
        $directory = ReviewPhotoStorage::absoluteDirectory();

        if (! File::isDirectory($directory)) {
            $this->components->twoColumnDetail('Foto review yatim', 'folder tidak ada');

            return 0;
        }

        $result = ReviewPhotoStorage::pruneOrphans($apply);

        $this->components->twoColumnDetail(
            'Foto review yatim',
            ($apply ? $result['deleted'] : count(ReviewPhotoStorage::orphanFiles()))
                .' file / '.$this->formatBytes($result['bytes'])
        );

        return $apply ? $result['bytes'] : 0;
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