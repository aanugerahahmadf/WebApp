<?php

namespace Tests\Feature\Shared\CbirGalleryIndexingTest;

use App\Models\Category\Category;
use App\Models\Package\Package;
use App\Models\Catalog\Catalog;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Guard untuk bug "index CBIR menyusut diam-diam".
 *
 * `/api/index/add` di AI Core SEHARUSNYA menambahkan satu gambar dan mengganti
 * entri hanya bila FILE yang sama di-index ulang. dulu endpoint itu menghapus
 * semua entri milik `(type, owner_id)` yang sama. Karena `ai:sync` memanggilnya
 * satu kali per media, setiap produk punya beberapa gambar — hasilnya tiap
 * produk hanya menyisakan 1 gambar, indeks menyusut, dan tidak ada error pun.
 *
 * Test di sisi Laravel ini mengunci pemicunya: satu panggilan index per gambar
 * galeri, dengan owner_id yang sama.
 */
class CbirGalleryIndexingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Media Spatie butuh disk yang bisa ditulis.
        Storage::fake('public');
        config(['media-library.disk_name' => 'public']);

        // Transport gRPC dipakai DULUAN kalau ekstensi grpc tersedia dan
        // servernya hidup. Di mesin ini AI Core gRPC hidup di 127.0.0.1:50052,
        // jadi `indexMedia()` tidak pernah menyentuh HTTP -- dan Http::fake
        // tidak merekam apa pun.
        //
        // Test di file ini menguji KONTRAK "satu panggilan index per media"
        // lewat jalur HTTP, jadi transportnya dipatok ke HTTP di sini.
        // (Jalur gRPC punya testnya sendiri.)
        config(['services.ai_core_grpc_enabled' => false]);
    }

    public function test_every_gallery_image_of_one_catalog_is_indexed(): void
    {
        $category = Category::factory()->create();

        $catalog = Catalog::factory()->create(['category_id' => $category->id]);

        // Satu produk, tiga gambar galeri.
        //
        // Isi file harus byte PNG yang sungguhan, BUKAN string seperti
        // 'fake-image-bytes'. Media library menebak mime dari ISI file, dan
        // teks ASCII terdeteksi sebagai `text/plain`, yang ditolak
        // `acceptedFileTypes` dengan `FileUnacceptableForCollection`. Test-nya
        // lalu gagal di tempat yang sama sekali tidak berhubungan dengan yang
        // diuji (indeksasi CBIR), jadi penyebabnya tidak terlihat.
        // PNG 1x1 transparan adalah byte valid paling kecil yang bisa dipakai.
        foreach (['a', 'b', 'c'] as $suffix) {
            $path = "gallery-{$suffix}.png";
            Storage::disk('public')->put($path, self::onePixelPng());
            $catalog->addMedia(Storage::disk('public')->path($path))
                ->preservingOriginal()
                ->toMediaCollection('catalog_image');
        }

        $catalog->refresh()->load('media');
        $this->assertCount(3, $catalog->media, 'Fasiltur harus menghasilkan 3 media galeri.');

        // Tangkap semua permintaan ke AI Core.
        $calls = [];
        Http::fake([
            '*/api/index/add' => function (Request $request) use (&$calls) {
                $calls[] = $request->data();

                return Http::response(['success' => true, 'entry_id' => count($calls)]);
            },
            '*' => Http::response(['success' => true]),
        ]);

        // Perilaku yang harus dipertahankan: satu panggilan per media.
        $ownerId = $catalog->id;
        foreach ($catalog->media as $media) {
            app(\App\Services\CBIRService\CBIRService::class)->indexMedia($media);
        }

        $this->assertCount(
            3,
            $calls,
            'Setiap gambar galeri harus terkirim ke AI Core. Kalau hanya satu per produk, '
            .'AI Core akan menimpa dirinya sendiri dan galeri produk hilang.'
        );

        foreach ($calls as $index => $payload) {
            $this->assertSame(
                'catalog',
                $payload['metadata']['type'] ?? null,
                "Panggilan #".($index + 1).' harus menandai type=catalog.'
            );
            $this->assertSame(
                $ownerId,
                $payload['metadata']['owner_id'] ?? null,
                "Panggilan #".($index + 1).' harus memakai owner_id produk yang sama.'
            );
            $this->assertArrayHasKey(
                'image_path',
                $payload,
                "Panggilan #".($index + 1).' harus membawa image_path.'
            );
        }

        // owner_id sama untuk semua, image_path berbeda untuk semua.
        $paths = array_map(fn (array $c) => $c['image_path'], $calls);
        $this->assertCount(
            3,
            array_unique($paths),
            'Tiga panggilan harus menunjuk tiga file berbeda, bukan file yang sama berulang.'
        );
    }

    /**
     * Regression untuk bug morph map.
     *
     * `media.model_type` stores the morph-map ALIAS ("App\Models\Package"),
     * bukan FQCN. `indexMedia()` mencocokkannya ke `Package::class` sehingga
     * tidak pernah cocok dan semua media jatuh ke nilai default. Akibatnya
     * `/api/search/image` mencari paket di tabel catalogs, tidak menemukan
     * apa pun, dan membuang semua hasil secara diam-diam.
     */
    public function test_package_media_is_indexed_as_package_not_unknown(): void
    {
        $category = Category::factory()->create();
        $package  = Package::factory()->create(['category_id' => $category->id]);

        $path = 'package-image.png';
        Storage::disk('public')->put($path, self::onePixelPng());
        $package->addMedia(Storage::disk('public')->path($path))
            ->preservingOriginal()
            ->toMediaCollection('package_image');

        $package->refresh()->load('media');
        $this->assertCount(1, $package->media);

        $calls = [];
        Http::fake([
            '*/api/index/add' => function (Request $request) use (&$calls) {
                $calls[] = $request->data();

                return Http::response(['success' => true, 'entry_id' => 1]);
            },
            '*' => Http::response(['success' => true]),
        ]);

        app(\App\Services\CBIRService\CBIRService::class)->indexMedia($package->media->first());

        $this->assertCount(1, $calls);
        $this->assertSame(
            'package',
            $calls[0]['metadata']['type'] ?? null,
            'Media paket harus terindeks sebagai type=package. Nilai selain itu '
            .'berarti byImage() akan mencarinya di tabel catalogs dan membuangnya.'
        );
        $this->assertSame($package->id, $calls[0]['metadata']['owner_id'] ?? null);
    }

    /**
     * PNG 1x1 yang benar-benar valid, sebagai byte biner.
     *
     * Dipakai sebagai isi file gambar di test ini karena media library
     * menebak mime type dari ISI file, bukan dari ekstensi. String biasa
     * seperti 'fake-image-bytes' terdeteksi sebagai `text/plain` dan ditolak
     * `acceptedFileTypes` dengan `FileUnacceptableForCollection`, sehingga test
     * gagal di tempat yang tidak berhubungan dengan yang sedang diuji.
     *
     * Byte-nya bukan tebakan: kandidat base64 di sini sudah divalidasi dengan
     * `getimagesizefromstring()` DAN `imagecreatefromstring()`, karena ada
     * varian yang terbaca `image/png` tapi gagal saat GD membuat thumbnail
     * (`CouldNotLoadImage`, `libpng: invalid literal/length code`) -- gejalanya
     * muncul jauh dari tempat penyebabnya.
     */
    private static function onePixelPng(): string
    {
        return (string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true
        );
    }
}
