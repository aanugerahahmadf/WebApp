<?php

namespace App\Console\Commands\SyncCbirCsv;

use App\Models\Package\Package;
use App\Models\Product\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncCbirCsv extends Command
{
    protected $signature = 'cbir:sync
                            {--no-rebuild : Hanya export CSV, jangan rebuild index CBIR}
                            {--app-url= : Override APP_URL untuk image_url di dataset}';

    protected $description = 'Sync database products & packages ke dataset CSV, lalu rebuild CBIR index';

    public function handle(): int
    {
        $this->info('╔══════════════════════════════════════════╗');
        $this->info('║     CBIR Dataset Sync & Index Rebuild    ║');
        $this->info('╚══════════════════════════════════════════╝');

        // ── 1. Tentukan path CSV ──────────────────────────────────────────────
        $csvPath = base_path('../ai_core/data/dataset.csv');
        $csvDir = dirname($csvPath);

        if (! is_dir($csvDir)) {
            $this->error("Direktori tidak ditemukan: {$csvDir}");
            $this->line('Pastikan folder ai_core/data/ sudah ada.');

            return 1;
        }

        // ── 2. Export ke CSV ──────────────────────────────────────────────────
        $this->info('');
        $this->info('📦 Mengekspor data dari database...');

        $handle = fopen($csvPath, 'w');
        if ($handle === false) {
            $this->error("Gagal membuka file untuk ditulis: {$csvPath}");

            return 1;
        }

        // Header — harus cocok dengan yang dibaca rebuild_index.py
        fputcsv($handle, ['ID', 'Type', 'Name', 'Category', 'Price', 'Discount_Price', 'Organizer', 'Image_Path', 'Description']);

        $productCount = 0;
        $packageCount = 0;
        $skipped = 0;

        // ── Products ──────────────────────────────────────────────────────────
        $products = Product::with(['category', 'vendor'])->get();
        foreach ($products as $product) {
            $media = $product->getFirstMedia('product_image');
            if (! $media) {
                $skipped++;

                continue;
            }

            $imagePath = $media->getPath();
            if (! file_exists($imagePath)) {
                $this->warn("  File tidak ada: {$imagePath}");
                $skipped++;

                continue;
            }

            fputcsv($handle, [
                $product->id,
                'product',
                $product->name,
                $product->category?->name ?? 'unknown',
                $product->price,
                $product->discount_price ?? '',
                $product->vendor?->store_name ?? '',
                $imagePath,
                $product->description ?? '',
            ]);
            $productCount++;
        }

        // ── Packages ──────────────────────────────────────────────────────────
        $packages = Package::with(['category', 'vendor'])->get();
        foreach ($packages as $package) {
            $media = $package->getFirstMedia('package_image');
            if (! $media) {
                $skipped++;

                continue;
            }

            $imagePath = $media->getPath();
            if (! file_exists($imagePath)) {
                $this->warn("  File tidak ada: {$imagePath}");
                $skipped++;

                continue;
            }

            fputcsv($handle, [
                $package->id,
                'package',
                $package->name,
                $package->category?->name ?? 'unknown',
                $package->price,
                $package->discount_price ?? '',
                $package->vendor?->store_name ?? '',
                $imagePath,
                $package->description ?? '',
            ]);
            $packageCount++;
        }

        fclose($handle);

        $this->info("  ✅ Products  : {$productCount}");
        $this->info("  ✅ Packages  : {$packageCount}");
        if ($skipped > 0) {
            $this->warn("  ⚠️  Diskip    : {$skipped} (media tidak ada)");
        }
        $this->info("  📄 CSV saved : {$csvPath}");

        // ── 3. Rebuild CBIR Index via AI Core API ─────────────────────────────
        if ($this->option('no-rebuild')) {
            $this->info('');
            $this->info('ℹ️  --no-rebuild aktif. Lewati rebuild index.');

            return 0;
        }

        $this->info('');
        $this->info('🔄 Mengirim perintah rebuild ke AI Core...');

        $aiCoreUrl = rtrim((string) config('services.ai_core_url', 'http://127.0.0.1:5000'), '/');
        $appUrl = $this->option('app-url') ?: config('app.url', 'http://127.0.0.1:8000');

        try {
            $response = Http::timeout(300) // rebuild bisa lama
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

                // Invalidate CBIR cache di Laravel
                Cache::increment('cbir_cache_version');
                $this->info('  🗑️  Cache CBIR di-invalidate.');
            } else {
                $this->error('  ❌ AI Core merespons error: '.$response->status());
                $this->error('  '.$response->body());
                Log::error('cbir:sync rebuild error', ['status' => $response->status(), 'body' => $response->body()]);

                return 1;
            }
        } catch (\Exception $e) {
            $this->error('  ❌ Gagal terhubung ke AI Core: '.$e->getMessage());
            $this->warn('  ℹ️  Pastikan AI Core berjalan di: '.$aiCoreUrl);
            $this->warn('  ℹ️  Atau jalankan manual: python rebuild_index.py');
            Log::error('cbir:sync connection error', ['error' => $e->getMessage()]);

            return 1;
        }

        $this->info('');
        $this->info('✨ Sync & Rebuild selesai!');

        return 0;
    }
}
