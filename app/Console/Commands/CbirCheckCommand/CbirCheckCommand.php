<?php

namespace App\Console\Commands\CbirCheckCommand;

use App\Models\Package\Package;
use App\Models\Product\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Membandingkan katalog database dengan indeks CBIR di AI Core.
 *
 * Kenapa command ini perlu
 * -----------------------
 * `/api/search/image` memetakan hasil AI Core ke model lewat `find($id)`.
 * Kalau indeks sudah basi — misalnya produk dihapus dari database, atau
 * `ai:sync` belum dijalankan setelah seeding — semua hit dibuang dan endpoint
 * terlihat seperti "tidak ada hasil mirip", padahal katalognya yang bermasalah.
 *
 * Command ini membuat drift itu terlihat eksplisit, lengkap dengan exit code
 * supaya bisa dipakai sebagai health check di CI maupun saat deploy.
 */
class CbirCheckCommand extends Command
{
    protected $signature = 'cbir:check
                            {--json : Keluarkan hasil dalam format JSON}
                            {--timeout=10 : Timeout HTTP ke AI Core, dalam detik}';

    protected $description = 'Cek sinkronisasi indeks CBIR (AI Core) terhadap katalog database';

    public function handle(): int
    {
        $aiCoreUrl  = rtrim((string) config('services.ai_core_url', 'http://127.0.0.1:5000'), '/');
        $timeout    = (int) $this->option('timeout');

        // ── 1. AI Core hidup? ────────────────────────────────────────────────
        try {
            $health = Http::timeout(max(1, min($timeout, 10)))
                ->get("{$aiCoreUrl}/health");
        } catch (\Throwable $e) {
            return $this->reportFailure('AI Core tidak bisa dihubungi', [
                'ai_core_url'     => $aiCoreUrl,
                'connection_error' => $e->getMessage(),
            ]);
        }

        if (! $health->successful()) {
            return $this->reportFailure('AI Core merespons tidak sukses', [
                'ai_core_url' => $aiCoreUrl,
                'status'      => $health->status(),
                'body'        => mb_substr($health->body(), 0, 300),
            ]);
        }

        // ── 2. Ambil statistik indeks ────────────────────────────────────────
        try {
            $status = Http::timeout($timeout)->get("{$aiCoreUrl}/status");
        } catch (\Throwable $e) {
            return $this->reportFailure('Gagal membaca /status dari AI Core', [
                'ai_core_url'     => $aiCoreUrl,
                'connection_error' => $e->getMessage(),
            ]);
        }

        if (! $status->successful()) {
            return $this->reportFailure('Endpoint /status AI Core gagal', [
                'status' => $status->status(),
                'body'   => mb_substr($status->body(), 0, 300),
            ]);
        }

        $indexed = (int) ($status->json('total_products') ?? 0);

        // ── 3. Hitung katalog yang seharusnya terindeks ─────────────────────
        // Hanya produk & paket yang punya media gambar di collection utama yang
        // relevan; sisanya tidak akan pernah muncul di hasil pencarian.
        $expectedProducts = Product::with(['media' => fn ($q) => $q->where('collection_name', 'product_image')])
            ->get()
            ->filter(fn ($p) => $p->media->isNotEmpty())
            ->count();

        $expectedPackages = Package::with(['media' => fn ($q) => $q->where('collection_name', 'package_image')])
            ->get()
            ->filter(fn ($p) => $p->media->isNotEmpty())
            ->count();

        $expected = $expectedProducts + $expectedPackages;
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
            'products_with_media'    => $expectedProducts,
            'packages_with_media'    => $expectedPackages,
            'missing_from_index'     => $missing,
            'extra_in_index'         => $extra,
            'healthy'                => $healthy,
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $healthy ? self::SUCCESS : self::FAILURE;
        }

        $this->line('');
        $this->line('  AI Core        : '.$aiCoreUrl);
        $this->line('  Versi / method : '.($health->json('version') ?? '?').' / '.($health->json('method') ?? '?').' / '.($health->json('metric') ?? '?'));
        $this->line('  Terindeks     : '.$indexed);
        $this->line('  Seharusnya    : '.$expected.'  (produk '.$expectedProducts.', paket '.$expectedPackages.')');

        if ($expected === 0) {
            $this->error('');
            $this->error('  Tidak ada produk/paket yang punya gambar. Katalog kosong —');
            $this->error('  CBIR tidak akan pernah mengembalikan hasil. Jalankan seeding dulu.');

            return self::FAILURE;
        }

        if ($missing > 0) {
            $this->error('  Kurang        : '.$missing.' item belum terindeks.');
        }

        if ($extra > 0) {
            $this->warn('  Kelebihan     : '.$extra.' entri di indeks menunjuk item yang tidak ada / tidak punya gambar.');
        }

        if ($healthy) {
            $this->info('');
            $this->info('  Indeks sinkron dengan katalog.');

            return self::SUCCESS;
        }

        $this->error('');
        $this->error('  Indeks TIDAK sinkron dengan katalog.');
        $this->error('  Jalankan: php artisan cbir:sync');

        return self::FAILURE;
    }

    /**
     * Cetak hasil gagal lalu kembalikan exit code failure.
     *
     * Namanya `reportFailure`, bukan `fail`, karena `Command` sudah punya
     * method publik `fail()` dan menimpanya akan menyebabkan fatal error.
     *
     * @param  array<string, mixed>  $context
     */
    private function reportFailure(string $message, array $context = []): int
    {
        if ($this->option('json')) {
            $this->line((string) json_encode(
                array_merge(['healthy' => false, 'error' => $message], $context),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            ));

            return self::FAILURE;
        }

        $this->error('');
        $this->error('  '.$message);

        foreach ($context as $key => $value) {
            if (is_scalar($value)) {
                $this->line('    '.$key.': '.$value);
            }
        }

        return self::FAILURE;
    }
}
