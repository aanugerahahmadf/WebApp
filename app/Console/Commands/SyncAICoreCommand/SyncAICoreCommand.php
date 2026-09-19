<?php

namespace App\Console\Commands\SyncAICoreCommand;

use App\Models\Package\Package;
use App\Models\Product\Product;
use App\Services\CBIRService\CBIRService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncAICoreCommand extends Command
{
    protected $signature = 'ai:sync
                            {--skip-index : Lewati indexing satu-per-satu via /api/index/add}
                            {--rebuild-only : Hanya rebuild dari CSV yang sudah ada, tanpa export ulang}
                            {--app-url= : Override APP_URL untuk image_url di dataset}';

    protected $description = 'Sync semua products & packages ke AI Core index (export CSV + rebuild CBIR)';

    public function handle(CBIRService $cbirService): int
    {
        $this->info('╔══════════════════════════════════════════╗');
        $this->info('║     AI Core Comprehensive Sync           ║');
        $this->info('╚══════════════════════════════════════════╝');

        $aiCoreUrl = rtrim((string) config('services.ai_core_url', 'http://127.0.0.1:5000'), '/');
        $appUrl = $this->option('app-url') ?: config('app.url', 'http://127.0.0.1:8000');
        $csvPath = base_path('../ai_core/data/dataset.csv');

        // ── Mode: rebuild-only ────────────────────────────────────────────────
        if ($this->option('rebuild-only')) {
            $this->info('');
            $this->info('🔄 Mode: rebuild-only — menggunakan CSV yang sudah ada.');

            return $this->triggerRebuild($aiCoreUrl, $csvPath, $appUrl);
        }

        // ── 1. Export CSV ─────────────────────────────────────────────────────
        $this->info('');
        $this->info('📦 Mengekspor data dari database ke CSV...');

        $csvDir = dirname($csvPath);
        if (! is_dir($csvDir)) {
            $this->error("Direktori tidak ditemukan: {$csvDir}");

            return 1;
        }

        $products = Product::with('media', 'category', 'vendor')->get();
        $packages = Package::with('media', 'category', 'vendor')->get();

        $csvData = [];
        $csvHeader = ['ID', 'Type', 'Name', 'Category', 'Price', 'Discount_Price', 'Organizer', 'Image_Path', 'Description'];

        $totalMedia = 0;

        foreach ($products as $product) {
            foreach ($product->media as $media) {
                $imagePath = $media->getPath();
                if (! file_exists($imagePath)) {
                    continue;
                }

                $csvData[] = [
                    $product->id,
                    'product',
                    $product->name,
                    $product->category?->name ?? 'unknown',
                    $product->price,
                    $product->discount_price ?? '',
                    $product->vendor?->store_name ?? '',
                    $imagePath,
                    $product->description ?? '',
                ];
                $totalMedia++;
            }
        }

        foreach ($packages as $package) {
            foreach ($package->media as $media) {
                $imagePath = $media->getPath();
                if (! file_exists($imagePath)) {
                    continue;
                }

                $csvData[] = [
                    $package->id,
                    'package',
                    $package->name,
                    $package->category?->name ?? 'unknown',
                    $package->price,
                    $package->discount_price ?? '',
                    $package->vendor?->store_name ?? '',
                    $imagePath,
                    $package->description ?? '',
                ];
                $totalMedia++;
            }
        }

        // Tulis CSV
        $fp = fopen($csvPath, 'w');
        fputcsv($fp, $csvHeader);
        foreach ($csvData as $row) {
            fputcsv($fp, $row);
        }
        fclose($fp);

        $this->info("  ✅ Total media diekspor : {$totalMedia}");
        $this->info("  📄 CSV saved            : {$csvPath}");

        // ── 2. (Opsional) Index satu-per-satu via /api/index/add ─────────────
        if (! $this->option('skip-index')) {
            $this->info('');
            $this->info('🔗 Indexing satu-per-satu via /api/index/add...');

            $bar = $this->output->createProgressBar($totalMedia);
            $bar->start();

            foreach ($products as $product) {
                foreach ($product->media as $media) {
                    $cbirService->indexMedia($media);
                    $bar->advance();
                }
            }
            foreach ($packages as $package) {
                foreach ($package->media as $media) {
                    $cbirService->indexMedia($media);
                    $bar->advance();
                }
            }

            $bar->finish();
            $this->newLine();
        }

        // ── 3. Rebuild index dari CSV ─────────────────────────────────────────
        $this->info('');

        return $this->triggerRebuild($aiCoreUrl, $csvPath, $appUrl);
    }

    private function triggerRebuild(string $aiCoreUrl, string $csvPath, string $appUrl): int
    {
        $this->info('🔄 Mengirim perintah rebuild ke AI Core...');

        try {
            $response = Http::timeout(300)
                ->post("{$aiCoreUrl}/api/index/rebuild-from-dataset", [
                    'csv_path' => $csvPath,
                    'app_url' => $appUrl,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $this->info('  ✅ Rebuild selesai!');
                $this->info('  📊 Terindeks  : '.($data['total'] ?? '?'));
                $this->info('  ⏭️  Diskip     : '.($data['skipped'] ?? '?'));
                if (! empty($data['errors'])) {
                    $this->warn('  ❌ Error      : '.$data['errors']);
                }
                if (! empty($data['categories'])) {
                    $cats = collect($data['categories'])->map(fn ($v, $k) => "{$k}:{$v}")->implode(', ');
                    $this->info("  🏷️  Kategori   : {$cats}");
                }
                if (! empty($data['types'])) {
                    $types = collect($data['types'])->map(fn ($v, $k) => "{$k}:{$v}")->implode(', ');
                    $this->info("  📦 Tipe       : {$types}");
                }
                if (! empty($data['elapsed_seconds'])) {
                    $this->info('  ⏱️  Waktu      : '.$data['elapsed_seconds'].'s');
                }

                // Invalidate CBIR cache
                Cache::increment('cbir_cache_version');
                $this->info('  🗑️  Cache CBIR di-invalidate.');
                $this->info('');
                $this->info('✨ Sync selesai! CBIR siap digunakan.');

                return 0;
            } else {
                $this->error('  ❌ AI Core error: '.$response->status());
                $this->error('  '.$response->body());
                Log::error('ai:sync rebuild error', ['status' => $response->status(), 'body' => $response->body()]);

                return 1;
            }
        } catch (\Exception $e) {
            $this->error('  ❌ Gagal terhubung ke AI Core: '.$e->getMessage());
            $this->warn('  ℹ️  Pastikan AI Core berjalan di: '.$aiCoreUrl);
            $this->warn('  ℹ️  Atau jalankan manual: python rebuild_index.py --csv '.$csvPath);
            Log::error('ai:sync connection error', ['error' => $e->getMessage()]);

            return 1;
        }
    }
}
