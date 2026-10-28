<?php

use App\Models\Catalog\Catalog;
use App\Models\Order\Order;
use App\Models\PaymentMethod\PaymentMethod;
use App\Models\User\User;
use App\Support\QrisImage\QrisImage;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Gambar QRIS toko
|--------------------------------------------------------------------------
|
| QRIS sekarang memakai berkas statis `public/images/qris/qris.jpg`
| (QRIS GPN "TIMELESS FLORALS - FLORIST SHOP", NMID ID1026626838399).
|
| Sebelumnya semua view membaca kolom `payment_methods.image_url` yang
| nilainya NULL, jadi halaman checkout hanya menampilkan badge
| "Gambar QRIS tidak tersedia" -- tanpa error apa pun. Test ini mengunci
| sumber gambarnya supaya kolom kosong tidak bisa kembali dipakai.
|
*/

function makeQrisPaymentMethod(): PaymentMethod
{
    return PaymentMethod::create([
        'name' => 'QRIS (Semua Bank & E-Wallet)',
        'type' => 'qris',
        'code' => 'qris-test',
        'bank_name' => 'BRI',
        'account_number' => '421201032041536',
        'account_holder' => 'Anugerah Ahmad Fachrurochim',
        'image_url' => null,
        'instructions' => 'Scan gambar QRIS toko.',
        'fee' => 0,
        'sort_order' => 2,
        'is_active' => true,
    ]);
}

test('berkas gambar QRIS toko benar-benar ada', function () {
    expect(QrisImage::exists())->toBeTrue()
        ->and(str_replace('\\', '/', QrisImage::path()))->toEndWith('images/qris/qris.jpg')
        ->and(filesize(QrisImage::path()))->toBeGreaterThan(10_000);
});

test('url gambar QRIS menunjuk berkas statis di folder public', function () {
    expect(QrisImage::url())
        ->toBeString()
        ->toEndWith('/images/qris/qris.jpg');
});

test('metode pembayaran QRIS mengirim qris_image_url lewat toArray', function () {
    $method = makeQrisPaymentMethod();

    expect($method->qris_image_url)->toEndWith('/images/qris/qris.jpg')
        ->and($method->toArray())->toHaveKey('qris_image_url')
        ->and($method->toArray()['qris_image_url'])->toEndWith('/images/qris/qris.jpg');
});

test('metode pembayaran non-QRIS tidak membawa gambar QRIS', function () {
    $cash = PaymentMethod::create([
        'name' => 'Bayar di Tempat (Cash)',
        'type' => 'cash',
        'code' => 'cash-test',
        'fee' => 0,
        'sort_order' => 3,
        'is_active' => true,
    ]);

    expect($cash->qris_image_url)->toBeNull()
        ->and(PaymentMethod::isQris($cash->id))->toBeFalse()
        ->and(PaymentMethod::isQris(makeQrisPaymentMethod()->id))->toBeTrue();
});

test('kartu QRIS bersama merender gambar merchant asli', function () {
    $html = view('Shared.components.qris-card.qris-card', [
        'method' => makeQrisPaymentMethod(),
    ])->render();

    expect(str_contains($html, '<img'))->toBeTrue()
        ->and(str_contains($html, '/images/qris/qris.jpg'))->toBeTrue()
        ->and(str_contains($html, __('Scan QRIS Berikut')))->toBeTrue()
        ->and(str_contains($html, __('Gambar QRIS tidak tersedia.')))->toBeFalse();
});

test('form checkout kedua panel menampilkan gambar QRIS saat metode QRIS dipilih', function () {
    $resources = [
        'User' => \App\Filament\User\Resources\CatalogResource\CatalogResource::class,
        'Welcome' => \App\Filament\Welcome\Resources\CatalogResource\CatalogResource::class,
    ];

    foreach ($resources as $panel => $resource) {
        $source = file_get_contents((new ReflectionClass($resource))->getFileName());

        expect(str_contains($source, "Placeholder::make('qris_preview')"))
            ->toBeTrue("Panel {$panel}: placeholder QRIS hilang");
        expect(str_contains($source, 'PaymentMethod::isQris'))
            ->toBeTrue("Panel {$panel}: visibilitas QRIS tidak dicek dari tipe metode");
        expect(str_contains($source, 'Shared.components.qris-card.qris-card'))
            ->toBeTrue("Panel {$panel}: kartu QRIS bersama tidak dipakai");
        expect(str_contains($source, '->live()'))
            ->toBeTrue("Panel {$panel}: Radio metode tidak live, gambar QRIS tidak muncul sebelum pindah step");
    }
});

test('halaman pembayaran pesanan memakai qris_image_url, bukan image_url', function () {
    foreach (['User', 'Welcome'] as $panel) {
        $path = base_path("resources/views/{$panel}/components/order-payment-info/order-payment-info.blade.php");

        expect(file_exists($path))->toBeTrue("Panel {$panel}: view order-payment-info tidak ada");

        $source = file_get_contents($path);

        // Cabang QRIS WAJIB membaca accessor `qris_image_url` (gambar statis
        // toko). `image_url` cuma boleh dipakai untuk logo bank.
        expect(str_contains($source, "type === 'qris' && \$pm?->qris_image_url"))
            ->toBeTrue("Panel {$panel}: cabang QRIS tidak memakai qris_image_url");
        expect(str_contains($source, '$pm->qris_image_url'))
            ->toBeTrue("Panel {$panel}: <img> QRIS tidak memakai accessor qris_image_url");
    }
});

test('halaman checkout Filament kedua panel merender gambar QRIS saat QRIS dipilih', function () {
    // `handleCheckout()` dan `mount()` membaca user lewat `Filament::auth()`,
    // jadi panel harus aktif dan ada user yang login.
    Filament::setCurrentPanel(Filament::getPanel('welcome'));

    actingAs(User::factory()->create(), 'web');

    $catalog = Catalog::factory()->create(['stock' => 5]);
    $qris = makeQrisPaymentMethod();

    $pages = [
        'User' => \App\Filament\User\Resources\CatalogResource\Pages\CheckoutCatalog\CheckoutCatalog::class,
        'Welcome' => \App\Filament\Welcome\Resources\CatalogResource\Pages\CheckoutCatalog\CheckoutCatalog::class,
    ];

    foreach ($pages as $panel => $page) {
        // Semua step wizard dirender di DOM (Filament menyembunyikan step
        // non-aktif lewat Alpine), jadi gambar QRIS harus sudah ada di HTML
        // begitu metode QRIS dipilih -- bukan setelah pindah step.
        Livewire::test($page, ['record' => $catalog->getKey()])
            ->fillForm(['payment_method_id' => $qris->id])
            ->assertSee('/images/qris/qris.jpg', false);
    }

    Filament::setCurrentPanel(null);
});

/*
 * Aplikasi HP menerima metode pembayaran lewat `OrderController`, yang
 * mengirim `PaymentMethod::toArray()`. Tanpa `qris_image_url` di payload,
 * Flutter hanya bisa mengandalkan fallback -- dan itu berarti field baru
 * di backend tidak pernah benar-benar terpakai.
 */
test('respons API payment-info membawa qris_image_url untuk aplikasi HP', function () {
    $user = User::factory()->create();

    $method = makeQrisPaymentMethod();

    $order = Order::create([
        'user_id' => $user->id,
        'order_number' => 'ORD-QRIS-'.uniqid(),
        'total_price' => 150000,
        'status' => 'pending',
        'payment_status' => 'unpaid',
        'booking_date' => now()->addDays(7),
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/orders/'.$order->id.'/payment-info');

    $response->assertOk()->assertJsonPath('status', 'success');

    $sent = collect($response->json('data.payment_methods'))->firstWhere('id', $method->id);

    expect($sent)->not->toBeNull()
        ->and($sent['qris_image_url'])->toEndWith('/images/qris/qris.jpg');
});
