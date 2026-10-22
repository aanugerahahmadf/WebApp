<?php

namespace App\Console\Commands\ExportCatalogs;

use App\Models\Catalog\Catalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use League\Csv\Writer;

class ExportCatalogs extends Command
{
    protected $signature = 'catalogs:export
                            {--format=csv : Output format (csv|xlsx)}
                            {--path= : Custom output path (default: storage/app/private/catalogs-export)}';

    protected $description = 'Export catalogs data to CSV/Excel (no images, text only)';

    public function handle(): int
    {
        $format = $this->option('format');
        $customPath = $this->option('path');

        $catalogs = Catalog::with(['category', 'tiers'])->get();

        if ($catalogs->isEmpty()) {
            $this->warn('No catalogs found.');
            return self::FAILURE;
        }

        $timestamp = now()->format('Ymd_His');
        $filename = "catalogs_export_{$timestamp}.{$format}";
        $outputPath = $customPath ?? storage_path("app/private/catalogs-export/{$filename}");

        File::ensureDirectoryExists(dirname($outputPath));

        $this->components->info("Exporting {$catalogs->count()} catalogs to {$format}...");

        if ($format === 'csv') {
            $this->exportCsv($catalogs, $outputPath);
        } else {
            $this->exportXlsx($catalogs, $outputPath);
        }

        $size = File::size($outputPath);
        $this->components->twoColumnDetail('File', basename($outputPath));
        $this->components->twoColumnDetail('Size', $this->formatBytes($size));
        $this->components->twoColumnDetail('Records', $catalogs->count());

        $this->info("Export completed: {$outputPath}");

        return self::SUCCESS;
    }

    protected function exportCsv($catalogs, string $path): void
    {
        $handle = fopen($path, 'w');
        
        // Write header
        fputcsv($handle, [
            'id',
            'slug',
            'name_id',
            'name_en',
            'description_id',
            'description_en',
            'price',
            'discount_pct',
            'stock',
            'category_slug',
            'category_name_id',
            'category_name_en',
            'package_names', // JSON array of package names
            'features',      // JSON array of features
            'image_references', // JSON array of image paths/URLs (text only)
        ]);

        foreach ($catalogs as $catalog) {
            $packages = $catalog->tiers->map(fn($p) => $p->name)->toArray();
            $features = $catalog->features ?? [];
            $imageRefs = $this->getImageReferences($catalog);

            fputcsv($handle, [
                $catalog->id,
                $catalog->slug,
                $catalog->name_id ?? $catalog->name,
                $catalog->name_en ?? $catalog->name,
                $catalog->desc_id ?? $catalog->description,
                $catalog->desc_en ?? $catalog->description,
                $catalog->price,
                $catalog->discount_pct ?? 0,
                $catalog->stock ?? 0,
                $catalog->category?->slug,
                $catalog->category?->name_id ?? $catalog->category?->name,
                $catalog->category?->name_en ?? $catalog->category?->name,
                json_encode($packages, JSON_UNESCAPED_UNICODE),
                json_encode($features, JSON_UNESCAPED_UNICODE),
                json_encode($imageRefs, JSON_UNESCAPED_UNICODE),
            ]);
        }

        fclose($handle);
    }

    protected function exportXlsx($catalogs, string $path): void
    {
        // For XLSX, we'll use CSV as fallback since League\Csv doesn't write XLSX directly
        // In production, you'd use PhpSpreadsheet
        $this->warn('XLSX not fully supported via League\\Csv, exporting as CSV instead.');
        $csvPath = str_replace('.xlsx', '.csv', $path);
        $this->exportCsv($catalogs, $csvPath);
        
        // Rename to .xlsx for user expectation (it's still CSV format)
        rename($csvPath, $path);
    }

    protected function getImageReferences(Catalog $catalog): array
    {
        $refs = [];
        
        // Get media from catalog_image collection
        foreach ($catalog->getMedia('catalog_image') as $media) {
            $refs[] = [
                'collection' => $media->collection_name,
                'file_name' => $media->file_name,
                'disk' => $media->disk,
                'path' => $media->getPath(),           // Local path (text reference only)
                'url' => $media->getUrl(),             // Public URL if accessible
                'mime_type' => $media->mime_type,
                'size' => $media->size,
            ];
        }

        // Also check gallery and category_image collections
        foreach (['gallery', 'category_image'] as $collection) {
            foreach ($catalog->getMedia($collection) as $media) {
                $refs[] = [
                    'collection' => $media->collection_name,
                    'file_name' => $media->file_name,
                    'disk' => $media->disk,
                    'path' => $media->getPath(),
                    'url' => $media->getUrl(),
                    'mime_type' => $media->mime_type,
                    'size' => $media->size,
                ];
            }
        }

        return $refs;
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) return $bytes.' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1).' KB';
        if ($bytes < 1073741824) return round($bytes / 1048576, 1).' MB';
        return round($bytes / 1073741824, 2).' GB';
    }
}