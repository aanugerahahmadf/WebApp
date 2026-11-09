<?php

use App\Http\Controllers\Api\User\CBIRController\CBIRController;
use App\Models\Catalog\Catalog;
use App\Services\CBIRService\CBIRService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Kontrak endpoint CBIR di sisi Laravel.
 *
 * Dua hal yang pernah salah dan tidak kelihatan dari layar:
 *
 * 1. `buildIndex()` mengindeks `getFirstMedia()` — satu foto per produk. Satu
 *    katalog punya LIMA foto galeri, jadi empat di antaranya tidak pernah masuk
 *    indeks dan pencarian dengan foto ke-2..5 tidak menemukan katalognya.
 *
 * 2. `getStats()` memetakan `total_catalogs` (jumlah FOTO) ke kunci
 *    `indexed_catalogs`, sehingga UI menampilkan ±500 "katalog" padahal
 *    produknya 100.
 */
beforeEach(function () {
    Storage::fake('public');

    config([
        'media-library.disk_name' => 'public',
        'services.ai_core_grpc_enabled' => false,
        // Membuka jalur tulis HANYA untuk test ini; tanpa ini `indexMedia()`
        // sengaja tidak memanggil AI Core (lihat CbirIndexWriteGuardTest).
        'services.ai_core_writes_in_tests' => true,
        'services.ai_core_url' => 'http://ai-core.test',
    ]);
});

function cbirContractPngBytes(): string
{
    return (string) base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
        true
    );
}

function cbirContractCatalog(int $photos): Catalog
{
    $catalog = Catalog::factory()->create();

    for ($i = 1; $i <= $photos; $i++) {
        $path = "contract-{$catalog->id}-{$i}.png";
        Storage::disk('public')->put($path, cbirContractPngBytes());

        $catalog->addMedia(Storage::disk('public')->path($path))
            ->preservingOriginal()
            ->toMediaCollection('catalog_image');
    }

    return $catalog->refresh()->load('media');
}

test('buildIndex mengindeks SEMUA foto galeri, bukan hanya foto pertama', function () {
    $catalog = cbirContractCatalog(3);

    $calls = [];
    Http::fake([
        '*/api/index/add' => function (Request $request) use (&$calls) {
            $calls[] = $request->data();

            return Http::response(['success' => true, 'entry_id' => count($calls)]);
        },
        '*' => Http::response(['success' => true]),
    ]);

    $payload = app(CBIRController::class)
        ->buildIndex(app(CBIRService::class))
        ->getData(true);

    expect($calls)->toHaveCount(3, 'Ketiga foto galeri harus dikirim ke AI Core.')
        ->and(array_unique(array_map(fn (array $c) => $c['image_path'], $calls)))
        ->toHaveCount(3, 'Tiap panggilan harus menunjuk berkas yang berbeda.')
        ->and($payload['indexed_catalogs'])->toBe(3)
        ->and($payload['errors'])->toBe([]);

    foreach ($calls as $call) {
        expect($call['metadata']['owner_id'])->toBe($catalog->id)
            ->and($call['metadata']['type'])->toBe('catalog');
    }
});

test('getStats membedakan jumlah foto dari jumlah produk', function () {
    Http::fake([
        '*/status' => Http::response([
            'status' => 'healthy',
            'total_catalogs' => 500,
            'total_entries' => 500,
            'total_items' => 100,
            'items_by_type' => ['catalog' => 100],
        ]),
    ]);

    $payload = app(CBIRController::class)
        ->getStats(app(CBIRService::class))
        ->getData(true);

    expect($payload['data']['indexed_catalogs'])->toBe(100, 'indexed_catalogs = produk unik.')
        ->and($payload['data']['indexed_entries'])->toBe(500, 'indexed_entries = baris indeks/foto.')
        ->and($payload['data']['indexed_items_by_type'])->toBe(['catalog' => 100]);
});

test('getStats tetap melaporkan angka saat AI Core versi lama', function () {
    // AI Core lama tidak mengirim `total_items`; jangan tampilkan 0.
    Http::fake([
        '*/status' => Http::response(['status' => 'healthy', 'total_catalogs' => 42]),
    ]);

    $payload = app(CBIRController::class)
        ->getStats(app(CBIRService::class))
        ->getData(true);

    expect($payload['data']['indexed_catalogs'])->toBe(42)
        ->and($payload['data']['indexed_entries'])->toBe(42);
});
