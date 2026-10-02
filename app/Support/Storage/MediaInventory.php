<?php

namespace App\Support\Storage;

use App\Support\MediaLibrary\CollectionPathGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Inventaris file di storage/app/public: daftar file, ukurannya, dan apakah
 * masih dirujuk data (reviews / tabel media).
 *
 * Satu sumber kebenaran untuk dua kebutuhan yang berbeda tapi saling related:
 *   - `media:prune`      -> menghapus yang statusnya "yatim"
 *   - `storage:inventory`-> melaporkan semuanya (CSV/XLSX)
 *
 * "Dirujuk" berarti ada baris yang menunjuk file itu:
 *   - review-photos/  -> reviews.photo atau reviews.photos (JSON list)
 *   - Products/, Packages/ -> baris di tabel `media`, nama file validnya
 *     dihitung lewat CollectionPathGenerator supaya class ini tidak perlu
 *     tahu bentuk layout disk.
 */
class MediaInventory
{
    /** Folder yang isinya diawasi, relatif ke storage/app/public. */
    public const FOLDERS = ['review-photos', 'Products', 'Packages'];

    /**
     * @return array<int, array{
     *   folder: string,
     *   file: string,
     *   relative_path: string,
     *   size_bytes: int,
     *   modified_at: string,
     *   status: string,
     *   referenced_by: string
     * }>
     */
    public function entries(): array
    {
        $publicRoot = $this->publicRoot();
        $referenced = $this->references();

        $entries = [];

        foreach (self::FOLDERS as $folder) {
            $dir = $publicRoot.'/'.$folder;

            if (! File::isDirectory($dir)) {
                continue;
            }

            foreach (File::files($dir) as $file) {
                $name = $file->getFilename();
                $ref = $referenced[$folder][$name] ?? null;

                $entries[] = [
                    'folder' => $folder,
                    'file' => $name,
                    'relative_path' => $folder.'/'.$name,
                    'size_bytes' => (int) $file->getSize(),
                    'modified_at' => date('Y-m-d H:i:s', (int) $file->getMTime()),
                    'status' => $ref === null ? 'yatim' : 'dipakai',
                    'referenced_by' => $ref ?? '-',
                ];
            }
        }

        usort($entries, fn ($a, $b) => [$a['folder'], $a['file']] <=> [$b['folder'], $b['file']]);

        return $entries;
    }

    /**
     * Ringkasan per folder + total.
     *
     * @param  array<int, array<string, mixed>>  $entries
     * @return array{folders: array<string, array{total: int, dipakai: int, yatim: int, bytes: int, orphan_bytes: int}>, total_bytes: int, orphan_bytes: int, total_files: int, orphan_files: int}
     */
    public function summarise(array $entries): array
    {
        $folders = [];

        foreach (self::FOLDERS as $folder) {
            $folders[$folder] = ['total' => 0, 'dipakai' => 0, 'yatim' => 0, 'bytes' => 0, 'orphan_bytes' => 0];
        }

        foreach ($entries as $e) {
            $f = &$folders[$e['folder']];
            $f['total']++;
            $f['bytes'] += $e['size_bytes'];

            if ($e['status'] === 'yatim') {
                $f['yatim']++;
                $f['orphan_bytes'] += $e['size_bytes'];
            } else {
                $f['dipakai']++;
            }
            unset($f);
        }

        $totalBytes = array_sum(array_column($folders, 'bytes'));
        $orphanBytes = array_sum(array_column($folders, 'orphan_bytes'));

        return [
            'folders' => $folders,
            'total_bytes' => $totalBytes,
            'orphan_bytes' => $orphanBytes,
            'total_files' => array_sum(array_column($folders, 'total')),
            'orphan_files' => array_sum(array_column($folders, 'yatim')),
        ];
    }

    /**
     * File yang masih dirujuk, dikelompokkan per folder.
     *
     * @return array<string, array<string, string>> folder => nama file => penunjuk
     */
    public function references(): array
    {
        return [
            'review-photos' => $this->referencedReviewPhotos(),
            'Products' => $this->referencedCatalogMedia('product_image', 'Products'),
            'Packages' => $this->referencedCatalogMedia('package_image', 'Packages'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function referencedReviewPhotos(): array
    {
        $keep = [];

        foreach (DB::table('reviews')->select('id', 'photo', 'photos')->cursor() as $row) {
            foreach ([$row->photo, $row->photos] as $value) {
                foreach ($this->decodePhotoPaths($value) as $path) {
                    if (! str_starts_with($path, 'review-photos/')) {
                        continue;
                    }

                    $keep[basename($path)] ??= 'reviews#'.$row->id;
                }
            }
        }

        return $keep;
    }

    /**
     * @return array<string, string>
     */
    protected function referencedCatalogMedia(string $collection, string $folder): array
    {
        $generator = app(CollectionPathGenerator::class);
        $keep = [];

        foreach (DB::table('media')->select('id', 'model_id', 'collection_name', 'file_name')->cursor() as $row) {
            if ($row->collection_name !== $collection) {
                continue;
            }

            $media = new Media([
                'id' => $row->id,
                'model_id' => $row->model_id,
                'collection_name' => $row->collection_name,
                'file_name' => $row->file_name,
            ]);

            $relative = $generator->getPath($media).$row->file_name;

            if (dirname($relative) !== $folder) {
                continue;
            }

            $keep[basename($relative)] = 'media#'.$row->id;
        }

        return $keep;
    }

    /**
     * Kolom `photo` berisi satu path, `photos` berisi JSON list.
     *
     * @return array<int, string>
     */
    protected function decodePhotoPaths(mixed $value): array
    {
        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        if (is_array($decoded)) {
            return array_values(array_filter($decoded, 'is_string'));
        }

        return [$value];
    }

    protected function publicRoot(): string
    {
        return storage_path('app/public');
    }
}
