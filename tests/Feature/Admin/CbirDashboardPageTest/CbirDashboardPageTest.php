<?php

use App\Filament\Admin\Pages\CbirDashboardPage\CbirDashboardPage;
use App\Models\Admin\Admin;
use App\Models\Cbir\CbirSearch;
use App\Models\Catalog\Catalog;
use App\Models\User\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/**
 * Halaman "CBIR & Pencarian Visual" (`/admin/cbir`).
 *
 * Halaman ini satu-satunya tempat admin bisa melihat:
 *   - apakah indeks AI Core masih sinkron dengan katalog,
 *   - angka evaluasi (MAP/MRR/first-rank/precision@3) yang dipakai di skripsi,
 *   - riwayat pencarian visual pengguna.
 *
 * Yang dijaga di sini bukan cuma "halamannya 200": angka dari `/status` harus
 * benar-benar TAMPIL, dan halaman harus tetap terbuka saat AI Core mati
 * (justru saat itu admin paling butuh melihat keadaannya).
 */
uses(RefreshDatabase::class);

/**
 * Pasang stub AI Core untuk satu test.
 *
 * PENTING: stub Laravel Http dievaluasi sesuai URUTAN PENDAFTARAN, dan yang
 * pertama cocok yang menang. Jadi stub `'*'` yang dipasang di `beforeEach`
 * akan MENELAN semua stub yang dipasang test di bawahnya -- test "AI Core
 * mati" tetap melihat status `healthy`, dan test "hitung ulang" tetap melihat
 * angka dari cache. Karena itu setiap test memasang stubnya sendiri, dan
 * helper ini dipanggil paling awal.
 */
function cbirPageFakeAiCore(array $overrides = []): void
{
    Http::fake($overrides + [
        '*/status' => Http::response([
            'status' => 'healthy',
            'version' => '2.0.0',
            'method' => 'combined',
            'metric' => 'cosine',
            'total_catalogs' => 500,
            'total_entries' => 500,
            'total_items' => 100,
            'items_by_type' => ['catalog' => 100],
        ]),
        '*/api/evaluate*' => Http::response([
            'success' => true,
            'cached' => true,
            'generated_at' => '2026-10-28 21:30:00',
            'n_queries' => 493,
            'metrics' => [
                'map' => 0.4222,
                'mrr' => 0.6178,
                'first_rank_accuracy' => 0.5254,
                'precision_at_3' => 0.5254,
                'n_queries' => 493,
            ],
        ]),
        '*' => Http::response(['status' => 'healthy']),
    ]);
}

beforeEach(function (): void {
    // HANYA role & permission. `$this->seed()` penuh ikut menjalankan
    // CatalogSeeder (100 katalog + 500 media, masing-masing dengan tiga
    // konversi gambar non-queued) -- halaman ini hanya butuh role
    // `super_admin` supaya navigasi panelnya bisa dirender.
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

    $admin = Admin::factory()->create();
    $admin->assignRole('super_admin');
    $this->actingAs($admin, 'admin');

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

test('halaman CBIR admin terbuka dan menampilkan angka indeks', function (): void {
    cbirPageFakeAiCore();

    $response = $this->get('/admin/cbir');

    $response->assertOk()
        ->assertSee(__('Kesehatan Indeks CBIR'))
        ->assertSee(__('Evaluasi Kualitatif'))
        ->assertSee(__('Riwayat Pencarian Visual'));

    // Dua angka yang berbeda artinya: 500 FOTO, 100 PRODUK.
    expect($response->getContent())
        ->toContain('500')
        ->toContain('100');
});

test('angka evaluasi ditampilkan apa adanya', function (): void {
    cbirPageFakeAiCore();

    $response = $this->get('/admin/cbir');

    $response->assertOk()
        ->assertSee('0.4222')
        ->assertSee('0.6178')
        ->assertSee('52.54%');
});

test('riwayat pencarian visual tampil dengan pengguna dan kemiripannya', function (): void {
    cbirPageFakeAiCore();

    $user = User::factory()->create(['full_name' => 'Siti Aminah']);
    $catalog = Catalog::factory()->create();

    $search = CbirSearch::create([
        'user_id' => $user->id,
        'query_image_url' => 'cbir-queries/contoh.jpg',
        'results_count' => 3,
        'top_similarity' => 96.5,
    ]);

    $search->results()->create([
        'type' => 'catalog',
        'item_id' => $catalog->id,
        'similarity' => 96.5,
        'score' => 0.965,
    ]);

    $this->get('/admin/cbir')
        ->assertOk()
        ->assertSee('Siti Aminah')
        ->assertSee('96.50')
        ->assertSee($catalog->name);
});

test('halaman tetap terbuka saat AI Core mati', function (): void {
    // Semua panggilan AI Core gagal: halaman tidak boleh 500, justru di sinilah
    // admin perlu melihat keadaannya.
    Http::fake(['*' => Http::response('', 500)]);

    $this->get('/admin/cbir')
        ->assertOk()
        ->assertSee(__('AI Core tidak menjawab'))
        ->assertSee(__('Belum ada angka evaluasi.'));
});

test('tombol hitung ulang memakai hasil baru dari AI Core', function (): void {
    cbirPageFakeAiCore([
        // Tanpa `*` bawaan: yang diuji hanya panggilan evaluasi.
        '*/api/evaluate?refresh=1' => Http::response([
            'success' => true,
            'cached' => false,
            'n_queries' => 10,
            'metrics' => [
                'map' => 0.7777,
                'mrr' => 0.8888,
                'first_rank_accuracy' => 0.9,
                'precision_at_3' => 0.9,
            ],
        ]),
    ]);

    Livewire::test(CbirDashboardPage::class)
        ->call('recomputeEvaluation')
        ->assertOk()
        ->assertSee('0.7777')
        ->assertDontSee(__('memakai hasil tersimpan'));

    // `?refresh=1` harus benar-benar diteruskan, bukan memakai cache.
    Http::assertSent(fn ($request) => str_contains($request->url(), '/api/evaluate?refresh=1'));
});
