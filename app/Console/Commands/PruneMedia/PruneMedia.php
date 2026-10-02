<?php

namespace App\Console\Commands\PruneMedia;

use App\Support\Storage\MediaInventory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Bersihkan file di storage/app/public yang tidak lagi dirujuk data mana pun.
 *
 * Dua sumber file di sana sama-sama bisa menumpuk tanpa batas:
 *
 *   1. review-photos/ -- ReviewSeeder menyalin foto dengan nama acak tiap run.
 *      Foto lama tidak pernah dihapus, jadi satu kali seed = ratusan file
 *      yang langsung menjadi sampah (pernah menumpuk 7 GB).
 *
 *   2. Products/ & Packages/ -- layout datar hasil CollectionPathGenerator.
 *      Nama file yang valid adalah yang dihitung baris tabel `media`; file
 *      lain di folder itu duplikat atau sisa.
 *
 * Aman dijalankan kapan saja: yang dihapus hanya file yang tidak dirujuk.
 * Default laporan saja; tambahkan --apply untuk benar-benar menghapus.
 *
 *   php artisan media:prune
 *   php artisan media:prune --apply
 */
class PruneMedia extends Command
{
    protected $signature = 'media:prune
                            {--apply : Hapus file-nya (tanpa flag ini hanya laporan)}';

    protected $description = 'Hapus file di storage/app/public yang tidak dirujuk review atau tabel media';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $this->components->info($apply ? 'Mode: hapus.' : 'Mode: laporan (dry-run). Tambahkan --apply untuk menghapus.');

        $inventory = app(MediaInventory::class);
        $summary = $inventory->summarise($inventory->entries());
        $publicRoot = storage_path('app/public');

        $deleted = 0;

        foreach (MediaInventory::FOLDERS as $folder) {
            $dir = $publicRoot.'/'.$folder;
            $stats = $summary['folders'][$folder];

            if (! File::isDirectory($dir)) {
                continue;
            }

            $this->components->twoColumnDetail(
                $folder.'/',
                $stats['yatim'].' file yatim dari '.$stats['total'].' ('.$this->formatBytes($stats['orphan_bytes']).')'
            );

            if (! $apply || $stats['yatim'] === 0) {
                continue;
            }

            foreach (File::files($dir) as $file) {
                if ($this->isOrphan($inventory, $file->getFilename(), $folder)) {
                    File::delete($file->getPathname()) && $deleted++;
                }
            }
        }

        $this->newLine();
        $this->components->twoColumnDetail('Total akan dihapus', $summary['orphan_files'].' file ('.$this->formatBytes($summary['orphan_bytes']).')');

        if (! $apply) {
            $this->components->info('Dry-run. Tambahkan --apply untuk menghapus.');

            return self::SUCCESS;
        }

        $this->components->info("Terhapus {$deleted} file. Storage lebih ringan.");

        return self::SUCCESS;
    }

    protected function isOrphan(MediaInventory $inventory, string $name, string $folder): bool
    {
        $references = $inventory->references();

        return ! isset($references[$folder][$name]);
    }

    protected function formatBytes(int $bytes): string
    {
        return match (true) {
            $bytes < 1024 => $bytes.' B',
            $bytes < 1048576 => round($bytes / 1024, 1).' KB',
            $bytes < 1073741824 => round($bytes / 1048576, 1).' MB',
            default => round($bytes / 1073741824, 2).' GB',
        };
    }
}
