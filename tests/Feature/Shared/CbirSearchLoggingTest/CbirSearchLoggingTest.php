<?php

use App\Models\Catalog\Catalog;
use App\Models\Cbir\CbirSearch;
use App\Models\User\User;
use App\Support\CbirSearchLogger\CbirSearchLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

/**
 * Riwayat pencarian visual (tabel `cbir_searches` + `cbir_search_results`).
 *
 * Sampai 2026-10-28 kedua tabel itu berisi 0 baris walau fitur CBIR sudah
 * dipakai: tidak ada satu pun kode yang menulis ke sana. Akibatnya panel admin
 * tidak punya bukti pemakaian, dan "berapa kali dicari / seberapa mirip
 * hasilnya" tidak bisa dilaporkan.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');

    config([
        'media-library.disk_name' => 'public',
        // Jangan coba gRPC di test: channel yang mati membuat setiap pencarian
        // menunggu timeout sebelum jatuh ke HTTP.
        'services.ai_core_grpc_enabled' => false,
        'services.ai_core_url' => 'http://ai-core.test',
    ]);
});

function cbirLoggingPng(): string
{
    return (string) base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
        true
    );
}

test('pencarian dicatat sebagai satu baris + baris hasil', function (): void {
    $user = User::factory()->create();

    $search = CbirSearchLogger::record($user->id, 'cbir-queries/contoh.jpg', [
        ['type' => 'catalog', 'owner_id' => 7, 'similarity' => 92.34, 'score' => 0.9234],
        ['type' => 'catalog', 'data' => ['id' => 9], 'similarity' => 55.0, 'score' => 0.55],
        // Tanpa id produk: tidak bisa disimpan, dan tidak boleh menggagalkan.
        ['type' => 'catalog', 'similarity' => 10.0],
    ]);

    expect($search)->not->toBeNull()
        ->and($search->user_id)->toBe($user->id)
        ->and($search->results_count)->toBe(2)
        ->and($search->top_similarity)->toBe(92.34);

    expect($search->results()->count())->toBe(2)
        ->and($search->results()->orderByDesc('similarity')->first()->item_id)->toBe(7);
});

test('kemiripan skala 0..1 dinaikkan ke persen supaya satu kolom tidak campur', function (): void {
    $user = User::factory()->create();

    $search = CbirSearchLogger::record($user->id, null, [
        ['type' => 'catalog', 'owner_id' => 1, 'similarity' => 0.874, 'score' => 0.874],
    ]);

    expect($search->top_similarity)->toBe(87.4)
        ->and($search->query_image_url)->toBe('-');
});

test('tamu tidak dicatat karena kolom user_id wajib terisi', function (): void {
    expect(CbirSearchLogger::record(null, 'cbir-queries/x.jpg', [
        ['type' => 'catalog', 'owner_id' => 1, 'similarity' => 90.0],
    ]))->toBeNull();

    expect(CbirSearch::query()->count())->toBe(0);
});

test('foto kueri disimpan supaya admin bisa melihat apa yang dicari', function (): void {
    $user = User::factory()->create();

    $file = UploadedFile::fake()->createWithContent('kueri.png', cbirLoggingPng());

    $search = CbirSearchLogger::recordUpload($user->id, $file, [
        ['type' => 'catalog', 'owner_id' => 3, 'similarity' => 88.0, 'score' => 0.88],
    ]);

    expect($search->query_image_url)->toStartWith('cbir-queries/');
    Storage::disk('public')->assertExists($search->query_image_url);
});

test('POST /api/search/image mencatat pencarian aplikasi HP', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $catalog = Catalog::factory()->create();

    Http::fake([
        '*/api/search' => Http::response([
            'success' => true,
            'query_time_seconds' => 0.42,
            'results' => [
                ['type' => 'catalog', 'owner_id' => $catalog->id, 'similarity' => 96.5, 'score' => 0.965],
            ],
        ]),
    ]);

    $response = $this->post('/api/search/image', [
        'image' => UploadedFile::fake()->createWithContent('kueri.png', cbirLoggingPng()),
    ]);

    $response->assertOk();

    $search = CbirSearch::query()->first();

    expect($search)->not->toBeNull()
        ->and($search->user_id)->toBe($user->id)
        ->and($search->results_count)->toBe(1)
        ->and($search->top_similarity)->toBe(96.5)
        ->and($search->results()->first()->item_id)->toBe($catalog->id);
});
