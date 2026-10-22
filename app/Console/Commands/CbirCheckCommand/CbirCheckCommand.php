<?php

namespace App\Console\Commands\CbirCheckCommand;

use App\Models\Catalog\Catalog;
use App\Models\Package\Package;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
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

        // Accept both the new canonical key and the legacy alias so the check
        // works during and after the AI Core rollout.
        $indexed = (int) ($status->json('total_catalogs') ?? 0);

        // ── 3. Hitung katalog yang seharusnya terindeks ─────────────────────
        // Hanya katalog yang punya media gambar di collection `catalog_image`;
        // paket tidak punya foto sendiri (foto ada di katalog), jadi tidak
        // dihitung di sini.
        $expectedCatalogs = Catalog::with(['media' => fn ($q) => $q->where('collection_name', 'catalog_image')])
            ->get()
            ->filter(fn ($c) => $c->media->isNotEmpty())
            ->count();

        $expected = $expectedCatalogs;
        $missing  = max(0, $expected - $indexed);
        $extra    = max(0, $indexed - $expected);
        $healthy = $expected > 0 && $missing === 0 && $extra === 0;

        $payload = [
            'ai_core_url'            => $aiCoreUrl,
            'ai_core_version'        => $health->json('version'),
            'ai_core_method'         => $health->json('method'),
            'ai_core_metric'         => $health->json('metric'),
            'indexed_entries'        => $indexed,
            'expected_items'         => $expected,
            'catalogs_with_media'    => $expectedCatalogs,
            'missing_from_index'     => $missing,
            'extra_in_index'         => $extra,
            'healthy'                => $healthy,
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->components->info('AI Core CBIR Health Check');
            $this->components->twoColumnDetail('AI Core URL', $aiCoreUrl);
            $this->components->twoColumnDetail('Versi', $health->json('version'));
            $this->components->twoColumnDetail('Metode', $health->json('method'));
            $this->components->twoColumnDetail('Metrik', $health->json('metric'));
            $this->components->twoColumnDetail('Tereindeks', (string) $indexed);
            $this->components->twoColumnDetail('Diharapkan', (string) $expected);
            $this->components->twoColumnDetail('Hilang', (string) $missing);
            $this->components->twoColumnDetail('Lebih', (string) $extra);
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