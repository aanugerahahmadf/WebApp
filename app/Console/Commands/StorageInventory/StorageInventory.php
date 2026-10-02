<?php

namespace App\Console\Commands\StorageInventory;

use App\Support\Storage\MediaInventory;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Ekspor inventaris file storage ke spreadsheet lokal (XLSX / CSV).
 *
 * Dipakai untuk melihat isi storage/app/public tanpa harus membuka File
 * Explorer di thousands file: daftar file, ukuran, dan apakah masih
 * dirujuk review / tabel media. File hasil bisa langsung di-import ke Google
 * Sheets (File > Import) tanpa perlu API atau kredensial apa pun.
 *
 *   php artisan storage:inventory              -> XLSX + ringkasan di terminal
 *   php artisan storage:inventory --csv        -> CSV (koma, tanpa formula)
 *   php artisan storage:inventory --path=...   -> tentukan lokasi sendiri
 */
class StorageInventory extends Command
{
    protected $signature = 'storage:inventory
                            {--csv : Tulis CSV, bukan XLSX}
                            {--path= : Lokasi file keluaran (default storage/app/private)}';

    protected $description = 'Ekspor daftar file storage/app/public beserta ukuran dan status dirujuk ke XLSX/CSV';

    public function handle(): int
    {
        $inventory = app(MediaInventory::class);
        $entries = $inventory->entries();
        $summary = $inventory->summarise($entries);

        $this->renderSummary($summary);

        if ($entries === []) {
            $this->components->warn('Tidak ada file untuk diekspor.');

            return self::SUCCESS;
        }

        $path = $this->outputPath();

        $csv = (bool) $this->option('csv');
        $this->writeFile($entries, $path, $csv);

        $this->components->twoColumnDetail('File keluaran', $path);
        $this->components->twoColumnDetail('Baris', (string) count($entries));
        $this->components->info('Bisa langsung di-import ke Google Sheets: File > Import > Upload.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, array{total: int, dipakai: int, yatim: int, bytes: int, orphan_bytes: int}>  $folders
     */
    protected function renderSummary(array $summary): void
    {
        $this->newLine();
        $this->components->twoColumnDetail('Total file', (string) $summary['total_files']);
        $this->components->twoColumnDetail('Total ukuran', $this->formatBytes($summary['total_bytes']));
        $this->components->twoColumnDetail('File yatim', $summary['orphan_files'].' ('.$this->formatBytes($summary['orphan_bytes']).')');
        $this->newLine();

        foreach ($summary['folders'] as $folder => $stats) {
            if ($stats['total'] === 0) {
                continue;
            }

            $this->components->twoColumnDetail(
                $folder.'/',
                $stats['dipakai'].' dipakai, '.$stats['yatim'].' yatim — '.$this->formatBytes($stats['bytes'])
            );
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     */
    protected function writeFile(array $entries, string $path, bool $csv): void
    {
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        if ($csv) {
            $handle = fopen($path, 'w');

            if ($handle === false) {
                $this->components->error('Gagal membuka file keluaran: '.$path);

                return;
            }

            // BOM supaya Excel/Sheets membaca UTF-8 (nama file bisa non-ASCII).
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['folder', 'file', 'path', 'size_bytes', 'size_kb', 'status', 'referenced_by', 'modified_at'], ',');

            foreach ($entries as $e) {
                fputcsv($handle, [
                    $e['folder'],
                    $e['file'],
                    $e['relative_path'],
                    $e['size_bytes'],
                    round($e['size_bytes'] / 1024, 1),
                    $e['status'],
                    $e['referenced_by'],
                    $e['modified_at'],
                ], ',');
            }

            fclose($handle);

            return;
        }

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Storage Inventory');

        $sheet->fromArray(
            ['Folder', 'File', 'Path', 'Ukuran (bytes)', 'Ukuran (KB)', 'Status', 'Dirujuk oleh', 'Diubah'],
            null,
            'A1'
        );

        $row = 2;
        foreach ($entries as $e) {
            $sheet->fromArray([
                $e['folder'],
                $e['file'],
                $e['relative_path'],
                $e['size_bytes'],
                round($e['size_bytes'] / 1024, 1),
                $e['status'],
                $e['referenced_by'],
                $e['modified_at'],
            ], null, 'A'.$row);
            $row++;
        }

        $sheet->getStyle('A1:H1')->getFont()->setBold(true);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:H1');

        $widths = ['A' => 14, 'B' => 34, 'C' => 40, 'D' => 15, 'E' => 12, 'F' => 10, 'G' => 14, 'H' => 20];
        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        // Sheet kedua: ringkasan per folder.
        $summarySheet = $spreadsheet->createSheet();
        $summarySheet->setTitle('Ringkasan');
        $summarySheet->fromArray(['Folder', 'Total file', 'Dipakai', 'Yatim', 'Ukuran', 'Ukuran yatim'], null, 'A1');

        $row = 2;
        foreach (array_keys($entries[0] ? array_unique(array_column($entries, 'folder')) : []) as $folder) {
            $stats = app(MediaInventory::class)->summarise($entries)['folders'][$folder] ?? null;

            if ($stats === null) {
                continue;
            }

            $summarySheet->fromArray([
                $folder,
                $stats['total'],
                $stats['dipakai'],
                $stats['yatim'],
                $stats['bytes'],
                $stats['orphan_bytes'],
            ], null, 'A'.$row);
            $row++;
        }

        $summarySheet->getStyle('A1:F1')->getFont()->setBold(true);
        $summarySheet->getColumnDimension('A')->setWidth(16);

        (new Xlsx($spreadsheet))->save($path);
    }

    protected function outputPath(): string
    {
        $directory = $this->option('path') ?: storage_path('app/private');
        $extension = $this->option('csv') ? 'csv' : 'xlsx';

        return rtrim($directory, '/\\').DIRECTORY_SEPARATOR.'storage-inventory-'.now()->format('Ymd-His').'.'.$extension;
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
