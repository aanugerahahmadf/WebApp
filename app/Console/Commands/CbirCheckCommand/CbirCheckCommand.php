<?php

namespace App\Console\Commands\CbirCheckCommand;

use App\Models\Catalog\Catalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CbirCheckCommand extends Command
{
    protected $signature = 'cbir:check
                            {--json : Cetak hasil sebagai JSON}
                            {--url= : Override AI_CORE_URL}';

    protected $description = 'Cek kesehatan AI Core CBIR service dan kesesuaian indeks.';

    public function handle(): int
    {
        $aiCoreUrl = $this->option('url') ?? config('services.ai_core_url', 'http://127.0.0.1:5000');

        $health = Http::timeout(10)->get("{$aiCoreUrl}/health");
        if (! $health->successful()) {
            return $this->reportFailure('Endpoint /health AI Core gagal', [
                'status' => $health->status(),
                'body'   => mb_substr($health->body(), 0, 300),
            ]);
        }

        $status = Http::timeout(15)->get("{$aiCoreUrl}/status");
        if (! $status->successful()) {
            return $this->reportFailure('Endpoint /status AI Core gagal', [
                'status' => $status->status(),
                'body'   => mb_substr($status->body(), 0, 300),
            ]);
        }

        // ── 1. Angka dari AI Core ───────────────────────────────────────────
        // `total_entries` / `total_catalogs` = jumlah FOTO di indeks (satu
        // katalog punya lima foto galeri). `total_items` = jumlah PRODUK unik
        // (type + owner_id) — hanya angka ini yang bisa dibandingkan dengan
        // jumlah katalog di database.
        //
        // Membandingkan jumlah foto dengan jumlah produk (perilaku lama)
        // selalu menghasilkan "Lebih: 261" dan kesimpulan "tidak sehat",
        // padahal indeksnya justru lengkap.
        $entries     = (int) ($status->json('total_entries') ?? $status->json('total_catalogs') ?? 0);
        $indexedItems = $status->json('total_items');
        $indexedItems = $indexedItems === null ? null : (int) $indexedItems;

        // ── 2. Yang seharusnya terindeks ────────────────────────────────────
        // Hanya katalog yang punya media gambar di collection `catalog_image`;
        // paket tidak punya foto sendiri (foto ada di katalog).
        $catalogsWithMedia = Catalog::query()
            ->whereHas('media', fn ($q) => $q->where('collection_name', 'catalog_image'))
            ->count();

        $expected = $catalogsWithMedia;
        $expectedEntries = (int) \Spatie\MediaLibrary\MediaCollections\Models\Media::query()
            ->where('collection_name', 'catalog_image')
            ->count();

        // Kalau AI Core lama belum mengirim `total_items`, jangan menebak:
        // laporkan angkanya sebagai null dan anggap sehat selama foto yang
        // terindeks tidak kurang dari jumlah media nyata.
        if ($indexedItems === null) {
            $missing = max(0, $expectedEntries - $entries);
            $extra   = max(0, $entries - $expectedEntries);
            $healthy = $expectedEntries > 0 && $missing === 0;
        } else {
            $missing = max(0, $expected - $indexedItems);
            $extra   = max(0, $indexedItems - $expected);
            $healthy = $expected > 0 && $missing === 0 && $extra === 0;
        }

        $payload = [
            'ai_core_url'            => $aiCoreUrl,
            'ai_core_version'        => $health->json('version'),
            'ai_core_method'         => $health->json('method'),
            'ai_core_metric'         => $health->json('metric'),
            // Entri = foto; item = produk. Keduanya dilaporkan supaya
            // selisih 5x tidak lagi disalahartikan sebagai indeks berlebih.
            'indexed_entries'        => $entries,
            'indexed_items'          => $indexedItems,
            'expected_items'         => $expected,
            'expected_entries'       => $expectedEntries,
            'catalogs_with_media'    => $catalogsWithMedia,
            'missing_from_index'     => $missing,
            'extra_in_index'         => $extra,
            'healthy'                => $healthy,
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->components->info('AI Core CBIR Health Check');
            $this->components->twoColumnDetail('AI Core URL', $aiCoreUrl);
            $this->components->twoColumnDetail('Versi', (string) $health->json('version'));
            $this->components->twoColumnDetail('Metode', (string) $health->json('method'));
            $this->components->twoColumnDetail('Metrik', (string) $health->json('metric'));
            $this->components->twoColumnDetail('Foto terindeks', (string) $entries);
            $this->components->twoColumnDetail('Foto seharusnya', (string) $expectedEntries);
            $this->components->twoColumnDetail('Produk terindeks', $indexedItems === null ? '-' : (string) $indexedItems);
            $this->components->twoColumnDetail('Produk seharusnya', (string) $expected);
            $this->components->twoColumnDetail('Hilang', (string) $missing);
            $this->components->twoColumnDetail('Berlebih', (string) $extra);
            $this->components->twoColumnDetail('Sehat', $healthy ? '✓' : '✗');
        }

        if ($healthy) {
            return self::SUCCESS;
        }

        return $this->reportFailure('Indeks CBIR tidak sehat', $payload);
    }

    private function reportFailure(string $message, array $context): int
    {
        Log::error('[CBIR Check] '.$message, $context);
        $this->components->error($message);
        $this->line(json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::FAILURE;
    }
}
