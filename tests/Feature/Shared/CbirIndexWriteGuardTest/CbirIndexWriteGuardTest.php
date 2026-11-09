<?php

use App\Models\Catalog\Catalog;
use App\Services\CBIRService\CBIRService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Penjaga: test suite TIDAK BOLEH menulis ke indeks CBIR yang sungguhan.
 *
 * Kejadian yang dikunci di sini: `phpunit.xml` memakai
 * `QUEUE_CONNECTION=sync`, jadi `IndexMediaToCBIR` berjalan langsung saat
 * media dibuat, dan `MediaObserver::deleted()` langsung memanggil
 * `removeFromIndex()`. Kalau AI Core hidup di mesin yang sama, satu kali
 * `php artisan test` akan:
 *
 *   - menambahkan entri untuk gambar palsu test, dan
 *   - MENGHAPUS entri untuk media yang dihapus test,
 *
 * sehingga `ai_core/data/metadata.json` menyusut (pernah 400 -> 361) tanpa
 * satu pun error di layar. Gejalanya baru terlihat jauh kemudian: mencari
 * foto sebuah produk mengembalikan produk lain, karena entri yang benar sudah
 * tidak ada.
 */
beforeEach(function () {
    Storage::fake('public');

    config([
        'media-library.disk_name' => 'public',
        // gRPC dicoba lebih dulu oleh CBIRService; matikan supaya jalur yang
        // diuji benar-benar HTTP dan `Http::assertNothingSent()` bermakna.
        'services.ai_core_grpc_enabled' => false,
        'services.ai_core_writes_in_tests' => false,
    ]);
});

/**
 * PNG 1x1 valid (bukan string sembarang): media library menebak mime dari
 * ISI berkas, dan teks ASCII ditolak `acceptedFileTypes`.
 */
function cbirWriteGuardPngBytes(): string
{
    return (string) base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
        true
    );
}

function cbirWriteGuardCatalogWithPhoto(): Catalog
{
    $catalog = Catalog::factory()->create();

    Storage::disk('public')->put('write-guard.png', cbirWriteGuardPngBytes());

    $catalog->addMedia(Storage::disk('public')->path('write-guard.png'))
        ->preservingOriginal()
        ->toMediaCollection('catalog_image');

    return $catalog->refresh()->load('media');
}

test('penulisan indeks dimatikan secara bawaan saat test suite', function () {
    expect(app(CBIRService::class)->writesAllowed())->toBeFalse();
});

test('membuat media tidak mengirim apa pun ke AI Core', function () {
    Http::fake(['*' => Http::response(['success' => true])]);

    cbirWriteGuardCatalogWithPhoto();

    // Kalau `IndexMediaToCBIR` (queue sync) lolos tanpa penjagaan, di sinilah
    // gambar test akan masuk ke metadata.json yang sungguhan.
    Http::assertNothingSent();
});

test('indexMedia dan removeFromIndex tidak menulis ke AI Core saat test', function () {
    Http::fake(['*' => Http::response(['success' => true])]);

    $catalog = cbirWriteGuardCatalogWithPhoto();
    $media = $catalog->media->first();
    $service = app(CBIRService::class);

    expect($service->indexMedia($media))->toBeTrue()
        ->and($service->removeFromIndex($media->id))->toBeTrue();

    Http::assertNothingSent();
});

test('penulisan bisa dibuka eksplisit untuk menguji jalur tulis', function () {
    config(['services.ai_core_writes_in_tests' => true]);

    Http::fake([
        '*/api/index/add' => Http::response(['success' => true, 'entry_id' => 1]),
        '*' => Http::response(['success' => true]),
    ]);

    $catalog = cbirWriteGuardCatalogWithPhoto();

    expect(app(CBIRService::class)->writesAllowed())->toBeTrue();

    app(CBIRService::class)->indexMedia($catalog->media->first());

    Http::assertSent(fn ($request) => str_contains($request->url(), '/api/index/add'));
});
