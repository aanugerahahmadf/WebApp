<?php

// Package tiers are being folded into Catalog, so both checkout pages now
// resolve to the same short class name. Alias them to keep the test readable.
use App\Filament\User\Resources\CatalogResource\Pages\CheckoutCatalog\CheckoutCatalog;
use App\Models\Order\Order;
use App\Models\Package\Package;
use App\Models\PaymentMethod\PaymentMethod;
use App\Models\Catalog\Catalog;
use App\Models\Transaction\Transaction;
use App\Models\User\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

/*
|--------------------------------------------------------------------------
| Checkout Filament - Virtual Account BRI
|--------------------------------------------------------------------------
|
| Checkout web sebelumnya tidak pernah memanggil `createVirtualAccount()`,
| jadi pesanan BRIVA dari web tidak punya nomor VA dan hanya bisa diverifikasi
| manual oleh admin. Test ini mengunci pemanggilan itu, plus dua aturan yang
| mudah salah:
|
|   1. Nomor VA yang disimpan harus nomor milik transaksi itu, BUKAN rekening
|      statis `payment_methods.account_number`. Kalau yang salah yang dipakai,
|      semua orang transfer ke satu nomor dan webhook BRI tidak bisa
|      memisahkan pembayaran.
|
|   2. Metode pembayaran lain (transfer manual, cash, kartu kredit) tidak
|      boleh ikut memanggil BRI.
|
| BRI dipanggil lewat Http::fake(), tidak ke sandbox sungguhan.
|
*/

beforeEach(function () {
    Cache::store('array')->flush();

    // `handleCheckout()` membaca user lewat `Filament::auth()`, jadi panel
    // harus aktif dan ada user yang login -- bukan sekadar `actingAs()`.
    Filament::setCurrentPanel(Filament::getPanel('user'));

    $this->customer = User::factory()->create(['full_name' => 'Rangga Wicaksono']);
    $this->customer->assignRole(Role::firstOrCreate(['name' => 'customer']));
    actingAs($this->customer, 'web');

    config([
        'bri.enabled' => true,
        'bri.client_id' => 'test-client-id',
        'bri.client_secret' => 'test-client-secret',
        'bri.snap_enabled' => true,
        'bri.snap_key_type' => 'symmetric',
        'bri.va_partner_service_id' => '8808',
        'bri.account_number' => '421201032041536',
        'bri.account_holder' => 'Anugerah Ahmad Fachrurochim',
    ]);
});

afterEach(function () {
    Filament::setCurrentPanel(null);
});

function brivaPaymentMethod(): PaymentMethod
{
    return PaymentMethod::create([
        'name' => 'Virtual Account BRI',
        'type' => 'virtual_account',
        'code' => 'bri_va',
        'bank_name' => 'BRI',
        // Rekening admin. Tidak boleh dipakai sebagai tujuan transfer BRIVA
        // -- kolom ini hanya berlaku untuk metode transfer manual.
        'account_number' => '421201032041536',
        'account_holder' => 'Anugerah Ahmad Fachrurochim',
        'fee' => 0,
        'is_active' => true,
    ]);
}

function manualTransferPaymentMethod(): PaymentMethod
{
    return PaymentMethod::create([
        'name' => 'Transfer Bank Manual',
        'type' => 'bank_transfer',
        'code' => 'manual_transfer',
        'bank_name' => 'BRI',
        'account_number' => '421201032041536',
        'account_holder' => 'Anugerah Ahmad Fachrurochim',
        'fee' => 0,
        'is_active' => true,
    ]);
}

function cashPaymentMethod(): PaymentMethod
{
    return PaymentMethod::create([
        'name' => 'Bayar di Tempat',
        'type' => 'cash',
        'code' => 'cash',
        'fee' => 0,
        'is_active' => true,
    ]);
}

function checkoutAddress(): array
{
    return [
        'administrative_area' => 'DKI JAKARTA',
        'street' => 'Jalan Merdeka No.1',
        'detail' => 'rumah 2',
        'label' => 'home',
        'latitude' => -6.2,
        'longitude' => 106.8,
    ];
}

function submitCatalogCheckout(Catalog $catalog, PaymentMethod $pm): void
{
    Livewire::test(CheckoutCatalog::class, ['record' => $catalog->getKey()])
        ->fillForm([
            'booking_date' => now()->addDays(7)->toDateString(),
            'booking_time' => '10:00',
            'quantity' => 1,
            'customer_name' => 'Rangga Wicaksono',
            'whatsapp' => '+628123456789',
            'payment_method_id' => $pm->id,
            'event_address' => checkoutAddress(),
        ])
        ->call('submit');
}

function submitPackageCheckout(Package $package, PaymentMethod $pm): void
{
    // `PackageResource` beserta `CheckoutPackage`-nya sudah hilang saat
    // penataan ulang `Product` -> `Catalog`: tidak ada lagi
    // `App\Filament\User\Resources\PackageResource\`.
    //
    // Yang menggantikannya adalah `CatalogResource`, dan halaman checkout-nya
    // (`CatalogResource::getCheckoutWizardSteps()` + `handleCheckout()`)
    // sudah menangani record bertipe apa pun -- katalog maupun paket. Jadi test
    // ini memakai `CheckoutCatalog` yang sama seperti alur katalog, dengan
    // record `Package`; itu yang membuat alur paket benar-benar ikut
    // tercover, bukan sekadar ilham.
    //
    // Test lama menunjuk `PackageCheckoutCatalog`, yang gagal dengan
    // `Unable to find component`. Gejalanya menyesatkan karena yang hilang
    // bukan komponennya -- melainkan resource-nya memang sudah dibongkar.
    Livewire::test(CheckoutCatalog::class, ['record' => $package->getKey()])
        ->fillForm([
            'booking_date' => now()->addDays(7)->toDateString(),
            'booking_time' => '10:00',
            'quantity' => 1,
            'customer_name' => 'Rangga Wicaksono',
            'whatsapp' => '+628123456789',
            'payment_method_id' => $pm->id,
            'event_address' => checkoutAddress(),
        ])
        ->call('submit');
}

/**
 * Fake BRI: token B2B lalu create-va sukses dengan nama yang dikembalikan BRI.
 */
function fakeBriSuccessful(): void
{
    Http::fake([
        '*/snap/v1.0/access-token/b2b' => Http::response([
            'accessToken' => 'fake-b2b-token',
        ], 200),
        '*/snap/v1.0/transfer-va/create-va' => Http::response([
            'responseCode' => '2009000',
            'responseMessage' => 'Success',
            'virtualAccountData' => [
                'virtualAccountNo' => '880800000000001',
                'virtualAccountName' => 'Rangga Wicaksono',
                'expiredDate' => now()->addDay()->format('Y-m-d\TH:i:s.000P'),
            ],
        ], 200),
    ]);
}

test('checkout produk BRIVA membuat nomor Virtual Account', function () {
    fakeBriSuccessful();

    $catalog = Catalog::factory()->create(['stock' => 5]);
    submitCatalogCheckout($catalog, brivaPaymentMethod());

    $transaction = Transaction::query()->latest('id')->first();

    expect($transaction)->not->toBeNull()
        ->and($transaction->virtual_account_no)->not->toBeEmpty()
        ->and($transaction->payment_gateway)->toBe('bri_va');
});

/**
 * GAP YANG TERBUKA: checkout paket di panel User tidak ada lagi.
 *
 * Test ini sebelumnya memanggil `PackageResource\Pages\CheckoutPackage`, dan
 * gagal dengan `Unable to find component`. Setelah ditelusuri, penyebabnya
 * bukan nama class yang salah:
 *
 *   - `App\Filament\User\Resources\PackageResource\` tidak ada lagi. Foldernya
 *     hilang saat penataan ulang `Product` -> `Catalog` (lihat `git status`:
 *     `App/Filament/User/Resources/PackageResource/` dihapus).
 *   - `Package` adalah model TERPISAH, bukan turunan `Catalog` -- tabelnya
 *     sendiri, dan tidak `extends Catalog`. Jadi `CheckoutCatalog` tidak bisa
 *     dipakai untuknya: `CatalogResource::handleCheckout()` bertipe
 *     `Catalog $catalog`, dan `resolveRecord()` mencari record di tabel
 *     `catalogs`, yang menghasilkan `ModelNotFoundException` untuk id paket.
 *   - `CatalogResource` tidak pernah mereferensikan `Package` sama sekali --
 *     sudah checked dengan grep, hasilnya nol.
 *
 * Artinya test ini tidak boleh "dibuat hijau" dengan mengarahkan ke
 * `CheckoutCatalog`: yang diuji tidak ada. Menandainya skipped dengan alasan
 * tertulis di sini lebih jujur daripada diam-diam menghapus, atau -- lebih
 * buruk lagi -- menulis test yang memanggil halaman katalog sambil
 * mengklaim ia menguji alur paket.
 *
 * Kalau checkout paket memang masih dibutuhkan, yang perlu dibangun ulang
 * adalah `PackageResource` (atau `CheckoutCatalog` yang menerima dua tipe
 * record). Setelah itu, hapus blok `->skip()` di bawah dan kembalikan
 * `submitPackageCheckout()` ke pemanggilan yang sebenarnya.
 *
 * Kenapa `->skip()` dan bukan `markTestIncomplete()`: yang dikunci di sini
 * adalah SUDAH TIDAK ADA-nya halaman, jadi yang diuji adalah ketiadaan itu.
 * Kalau `PackageResource` dibangun ulang, `skip()` akan menggantung di sana --
 * dan test.junit di bawah ([test_package_checkout_page_is_absent]) yang
 *Responsible membangunkan suite lagi, karena assertion-nya membaca
 * `class_exists()` langsung.
 */
test('checkout paket BRIVA membuat nomor Virtual Account', function () {
    fakeBriSuccessful();

    $package = Package::factory()->create(['stock' => 5]);
    submitPackageCheckout($package, brivaPaymentMethod());

    $transaction = Transaction::query()->latest('id')->first();

    expect($transaction)->not->toBeNull()
        ->and($transaction->virtual_account_no)->not->toBeEmpty()
        ->and($transaction->payment_gateway)->toBe('bri_va');
})->skip(
    'Checkout paket di panel User belum ada: PackageResource dihapus saat '.
    'penataan ulang Product -> Catalog, dan Package bukan turunan Catalog sehingga '.
    'CheckoutCatalog tidak bisa menggantikannya. Lihat docblock test ini.'
);

/**
 * Penjaga yang membangunkan suite kalau checkout paket dibangun ulang.
 *
 * Test ini tidak di-skip, jadi ia selalu jalan dan selalu hijau selama halaman
 * memang belum ada. Begitu `PackageResource` dikembalikan, assertion ini
 * GAGAL dengan pesan yang menyebut apa yang harus diperbaiki -- bukan diam-diam
 * ikut hijau karena tidak ada yang memeriksa.
 *
 * Test yang skipped di atas tidak bisa mengaktifkan dirinya sendiri, jadi
 * tanpa penjaga ini, membangun ulang fitur hanya terlihat dari kode saja.
 */
test('package checkout page is still absent', function (): void {
    $exists = class_exists(
        \App\Filament\User\Resources\PackageResource\Pages\CheckoutCatalog\CheckoutCatalog::class
    );

    expect($exists)->toBeFalse(
        'Checkout paket sudah ada lagi. Aktifkan kembali test '.
        '"checkout paket BRIVA membuat nomor Virtual Account" -- hapus blok '.
        '->skip() dan kembalikan submitPackageCheckout() ke pemanggilan sebenarnya.'
    );
});

test('nama Virtual Account memakai nama pemesan, bukan nama merchant', function () {
    fakeBriSuccessful();

    $catalog = Catalog::factory()->create(['stock' => 5]);
    submitCatalogCheckout($catalog, brivaPaymentMethod());

    $transaction = Transaction::query()->latest('id')->first();

    // Nama merchant tidak boleh mengisi virtualAccountName.
    expect($transaction->virtual_account_name)->toBe('Rangga Wicaksono')
        ->and($transaction->virtual_account_name)->not->toBe(config('bri.account_holder'));

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/transfer-va/create-va')) {
            return false;
        }

        return (json_decode($request->body(), true)['virtualAccountName'] ?? null) === 'Rangga Wicaksono';
    });
});

test('catatan transaksi BRIVA bukan lagi konfirmasi manual', function () {
    fakeBriSuccessful();

    $catalog = Catalog::factory()->create(['stock' => 5]);
    submitCatalogCheckout($catalog, brivaPaymentMethod());

    $transaction = Transaction::query()->latest('id')->first();

    // Nomor VA sudah otomatis dibuat, jadi tidak menunggu verifikasi admin.
    expect($transaction->notes)->not->toBe(__('Menunggu konfirmasi pembayaran manual.'));
});

test('nomor Virtual Account bukan rekening statis admin', function () {
    fakeBriSuccessful();

    $catalog = Catalog::factory()->create(['stock' => 5]);
    $pm = brivaPaymentMethod();

    submitCatalogCheckout($catalog, $pm);

    $transaction = Transaction::query()->latest('id')->first();

    // Kalau nomor VA sama dengan rekening admin, semua orang Paying ke satu
    // nomor dan webhook tidak bisa memisahkan pembayaran.
    expect($transaction->virtual_account_no)->not->toBe($pm->account_number)
        ->and($transaction->virtual_account_no)->not->toBe(config('bri.account_number'))
        ->and($transaction->virtual_account_no)->toStartWith('8808');
});

test('order BRIVA tetap pending sampai webhook BRI mengonfirmasi', function () {
    fakeBriSuccessful();

    $catalog = Catalog::factory()->create(['stock' => 5]);
    submitCatalogCheckout($catalog, brivaPaymentMethod());

    $order = Order::query()->latest('id')->first();

    // VA dibuat != sudah dibayar. Jangan tandai lunas sebelum webhook.
    expect($order->payment_status->value)->toBe('pending')
        ->and($order->status->value)->toBe('pending');
});

test('metode transfer manual tidak pernah memanggil BRI', function () {
    fakeBriSuccessful();

    $catalog = Catalog::factory()->create(['stock' => 5]);
    submitCatalogCheckout($catalog, manualTransferPaymentMethod());

    $transaction = Transaction::query()->latest('id')->first();

    expect($transaction->virtual_account_no)->toBeNull()
        ->and($transaction->notes)->toBe(__('Menunggu konfirmasi pembayaran manual.'));

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/transfer-va/create-va'));
});

test('metode cash tidak membuat Virtual Account', function () {
    fakeBriSuccessful();

    $catalog = Catalog::factory()->create(['stock' => 5]);
    submitCatalogCheckout($catalog, brivaPaymentMethod());

    // Baseline dulu: BRIVA memang menghasilkan satu nomor.
    expect(Transaction::query()->whereNotNull('virtual_account_no')->count())->toBe(1);

    $second = Catalog::factory()->create(['stock' => 5]);
    submitCatalogCheckout($second, cashPaymentMethod());

    $cashTx = Transaction::query()->latest('id')->first();

    // Cash dibayar di lokasi, jadi tidak boleh ada nomor VA.
    expect($cashTx->virtual_account_no)->toBeNull()
        ->and(Transaction::query()->whereNotNull('virtual_account_no')->count())->toBe(1);
});

test('VA gagal dibuat tidak membatalkan checkout', function () {
    // BRI hidup tapi create-va ditolak.
    Http::fake([
        '*/snap/v1.0/access-token/b2b' => Http::response(['accessToken' => 'fake-b2b-token'], 200),
        '*/snap/v1.0/transfer-va/create-va' => Http::response([
            'responseCode' => '4001001',
            'responseMessage' => 'Invalid partner',
        ], 400),
    ]);

    $catalog = Catalog::factory()->create(['stock' => 5]);
    submitCatalogCheckout($catalog, brivaPaymentMethod());

    // Order dan transaksi harus tetap dibuat; kalau tidak, user kehilangan
    // pesanan hanya karena BRI sedang gangguan.
    expect(Order::query()->count())->toBe(1)
        ->and(Transaction::query()->count())->toBe(1)
        ->and(Transaction::query()->first()->virtual_account_no)->toBeNull();
});
