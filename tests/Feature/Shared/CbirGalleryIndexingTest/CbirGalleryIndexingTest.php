<?php

namespace Tests\Feature\Shared;

use App\Models\Category\Category;
use App\Models\Package\Package;
use App\Models\Product\Product;
use App\Models\Vendor\Vendor;
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
    }

    public function test_every_gallery_image_of_one_product_is_indexed(): void
    {
        $category = Category::factory()->create();

        $product = Product::factory()->create(['category_id' => $category->id]);

        // Satu produk, tiga gambar galeri.
        foreach (['a', 'b', 'c'] as $suffix) {
            $path = "gallery-{$suffix}.png";
            Storage::disk('public')->put($path, 'fake-image-bytes');
            $product->addMedia(Storage::disk('public')->path($path))
                ->preservingOriginal()
                ->toMediaCollection('product_image');
        }

        $product->refresh()->load('media');
        $this->assertCount(3, $product->media, 'Fasiltur harus menghasilkan 3 media galeri.');

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
        $ownerId = $product->id;
        foreach ($product->media as $media) {
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
                'product',
                $payload['metadata']['type'] ?? null,
                "Panggilan #".($index + 1).' harus menandai type=product.'
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
     * `/api/search/image` mencari paket di tabel products, tidak menemukan
     * apa pun, dan membuang semua hasil secara diam-diam.
     */
    public function test_package_media_is_indexed_as_package_not_unknown(): void
    {
        $category = Category::factory()->create();
        $package  = Package::factory()->create(['category_id' => $category->id]);

        $path = 'package-image.png';
        Storage::disk('public')->put($path, 'fake-image-bytes');
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
            .'berarti byImage() akan mencarinya di tabel products dan membuangnya.'
        );
        $this->assertSame($package->id, $calls[0]['metadata']['owner_id'] ?? null);
    }

    /**
     * Nama item dan vendor ikut terisi sekarang.
     *
     * Sebelumnya baris pemanggilnya memakai `$media->model_type::with(...)`,
     * yaitu alias morph map yang bukan nama kelas. Panggilan statis itu gagal
     * dan tertangkap `catch (\Throwable)`, jadi `name` dan `vendor` terkirim
     * kosong tanpa satu pun error yang terlihat.
     */
    public function test_item_name_and_vendor_are_sent_to_the_index(): void
    {
        // Vendor tidak punya factory di database/factories, jadi dibuat manual.
        $vendor   = Vendor::create(['store_name' => 'Florist Nusantara']);
        $category = Category::factory()->create();
        $product  = Product::factory()->create([
            'category_id' => $category->id,
            'vendor_id'   => $vendor->id,
            'name'        => 'Gebyok Ukir Jati Premium',
        ]);

        $path = 'named-product.png';
        Storage::disk('public')->put($path, 'fake-image-bytes');
        $product->addMedia(Storage::disk('public')->path($path))
            ->preservingOriginal()
            ->toMediaCollection('product_image');

        $product->refresh()->load('media');

        $calls = [];
        Http::fake([
            '*/api/index/add' => function (Request $request) use (&$calls) {
                $calls[] = $request->data();

                return Http::response(['success' => true, 'entry_id' => 1]);
            },
            '*' => Http::response(['success' => true]),
        ]);

        app(\App\Services\CBIRService\CBIRService::class)->indexMedia($product->media->first());

        $this->assertCount(1, $calls);
        $this->assertSame('Gebyok Ukir Jati Premium', $calls[0]['metadata']['name'] ?? null);
        $this->assertSame('Florist Nusantara', $calls[0]['metadata']['vendor'] ?? null);
    }
}
