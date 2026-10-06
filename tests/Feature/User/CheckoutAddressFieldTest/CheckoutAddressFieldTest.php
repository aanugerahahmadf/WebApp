<?php

/**
 * Field alamat acara di checkout: `App\Forms\Components\CheckoutAddress`.
 *
 * Field ini menggantikan `Textarea::make('notes')` yang dulu jadi satu-satunya
 * tempat alamat acara diisi. Yang dijaga di sini:
 *
 *   1. `formatAddress()` -- SATU tempat yang merakit alamat jadi teks untuk
 *      `orders.notes`. Dipakai oleh view (ringkasan) DAN oleh `handleCheckout()`
 *      di PackageResource/ProductResource. Kalau ini dihitung di dua tempat
 *      (atau di JavaScript), string yang tersimpan bisa berbeda dari yang
 *      tampil, dan tidak ada test yang bisa memegangnya.
 *
 *   2. Urutan baris: detail -> jalan -> wilayah. Baris kosong tidak boleh ikut:
 *      alamat tanpa kota tidak berguna untuk tim dekorasi.
 *
 *   3. Halaman checkout masih merender field + tombol Edit + modal, di kedua
 *      panel: produk dan paket. Field yang hilang di salah satunya berarti
 *      checkout memaksa pengguna mengetik alamat manual tanpa koordinat.
 *
 *   4. Field WAJIB ada di state form (bukan sekadar tampil), karena
 *      `handleCheckout()` membaca `$data['event_address']`. Field yang hilang
 *      membuat alamat kosong tersimpan tanpa error.
 *
 *   5. `dehydrateStateUsing()` menerima string legacy (`notes` polos dari state
 *      lama / draft) dan memetakannya ke `street`, supaya alamat yang sudah
 *      terisi tidak hilang saat form dikirim ulang.
 *
 * Yang TIDAK diuji di sini: peta, Google Places Autocomplete, dan
 * `navigator.geolocation`. Semuanya berjalan di browser dan tidak punya runner di
 * suite ini -- yang dijaga di sisi server adalah bentuk state dan hasil rakitannya.
 */

use App\Filament\User\Resources\PackageResource\PackageResource;
use App\Filament\User\Resources\ProductResource\Pages\CheckoutProduct\CheckoutProduct;
use App\Filament\User\Resources\ProductResource\ProductResource;
use App\Forms\Components\CheckoutAddress\CheckoutAddress;
use App\Models\Package\Package;
use App\Models\Order\Order;
use App\Models\PaymentMethod\PaymentMethod;
use App\Models\Product\Product;
use App\Models\User\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('user'));

    $this->customer = User::factory()->create();
    $this->customer->assignRole(Role::firstOrCreate(['name' => 'customer']));

    actingAs($this->customer, 'web');
});

afterEach(function (): void {
    Filament::setCurrentPanel(null);
});

/*
 * ---------------------------------------------------------------- formatAddress
 */

test('the address is assembled detail first then street then area', function (): void {
    // Urutannya disengaja: yang paling menentukan (patokan) di atas, wilayah
    // paling kasar di bawah, seperti pada kolom "Alamat Lokasi" di checkout
    // lama dan seperti yang dibaca tukang dekorasi di lapangan.
    expect(CheckoutAddress::formatAddress([
        'administrative_area' => 'JAWA BARAT, KAB. INDRAMAYU, SINDANG, 45223',
        'street' => 'Jalan Babar Layar No.9, RT.37/RW.2',
        'detail' => 'no rumah 16',
    ]))->toBe("no rumah 16\nJalan Babar Layar No.9, RT.37/RW.2\nJAWA BARAT, KAB. INDRAMAYU, SINDANG, 45223");
});

test('blank parts are left out instead of leaving empty lines', function (): void {
    // Alamat tanpa detail adalah kasus paling sering (pengguna cuma pilih dari
    // autocomplete). Baris kosong di tengah akan membuat alamat terlihat rusak
    // ketika dicetak di struk.
    expect(CheckoutAddress::formatAddress([
        'street' => 'Jalan Melati No. 5',
        'administrative_area' => 'BANDUNG, SUMEDANG, 45391',
        'detail' => '   ',
    ]))->toBe("Jalan Melati No. 5\nBANDUNG, SUMEDANG, 45391");
});

test('an empty state produces an empty address instead of a stray newline', function (): void {
    expect(CheckoutAddress::formatAddress(CheckoutAddress::emptyState()))->toBe('')
        ->and(CheckoutAddress::formatAddress([]))->toBe('');
});

test('the empty state always carries the label and save flag defaults', function (): void {
    $state = CheckoutAddress::emptyState();

    // Tanpa default ini, view akan merender chip "Rumah" tidak terpilih dan
    // checkbox "jangan simpan" tidak tercentang -- keduanya jadi ambigu.
    expect($state['label'])->toBe('home')
        ->and($state['save_for_user'])->toBeFalse()
        ->and($state['latitude'])->toBeNull()
        ->and($state['formatted'])->toBeNull();
});

/*
 * ------------------------------------------------------------ state hydration
 */

test('the checkout saves the assembled address into the order notes', function (): void {
    // Uji end-to-end jalur yang dipakai user: pilih dari autocomplete/GPS, lalu
    // konfirmasi. Yang arrives di `orders.notes` harus hasil rakit
    // formatAddress() -- baris per baris, urut dari detail.
    $product = Product::factory()->create(['stock' => 5]);
    $paymentMethod = PaymentMethod::create(['name' => 'Transfer Bank', 'type' => 'bank_transfer', 'code' => 'bca', 'fee' => 0, 'is_active' => true]);

    Livewire::test(CheckoutProduct::class, ['record' => $product->getKey()])
        ->fillForm([
            'booking_date' => now()->addDays(7)->toDateString(),
            'booking_time' => '10:00',
            'quantity' => 1,
            'customer_name' => 'Rangga Wicaksono',
            'whatsapp' => '+628123456789',
            'payment_method_id' => $paymentMethod->id,
            'event_address' => [
                'administrative_area' => 'JAWA BARAT, KAB. INDRAMAYU, SINDANG, 45223',
                'street' => 'Jalan Babar Layar No.9, RT.37/RW.2',
                'detail' => 'no rumah 16',
                'label' => 'home',
                'latitude' => -6.6319,
                'longitude' => 107.9538,
            ],
        ])
        ->call('submit');

    $order = Order::query()->latest('id')->first();

    expect($order)->not->toBeNull()
        ->and($order->notes)->toBe("no rumah 16\nJalan Babar Layar No.9, RT.37/RW.2\nJAWA BARAT, KAB. INDRAMAYU, SINDANG, 45223")
        ->and($order->product_id)->toBe($product->id);
});

test('a legacy plain string address still reaches the order', function (): void {
    // Draft dari versi form lama (textarea `notes` polos) menyimpan string, bukan
    // array. Kalau tidak dipetakan di dehydrateStateUsing(), alamat lama hilang
    // begitu checkout dibuka lagi -- dan tidak ada error yang terlihat.
    //
    // Di-set lewat `set()`, bukan `fillForm()`: fillForm memetakan nilai ke
    // komponen di dalam array, dan memberinya string membuat pemetaan gagal --
    // persis kondisi yang harus kita hindari.
    $product = Product::factory()->create(['stock' => 5]);
    $paymentMethod = PaymentMethod::create(['name' => 'Transfer Bank', 'type' => 'bank_transfer', 'code' => 'bca', 'fee' => 0, 'is_active' => true]);

    Livewire::test(CheckoutProduct::class, ['record' => $product->getKey()])
        ->fillForm([
            'booking_date' => now()->addDays(7)->toDateString(),
            'booking_time' => '10:00',
            'quantity' => 1,
            'customer_name' => 'Rangga Wicaksono',
            'whatsapp' => '+628123456789',
            'payment_method_id' => $paymentMethod->id,
        ])
        ->set('data.event_address', 'Alamat lama, tidak terstruktur')
        ->call('submit');

    expect(Order::query()->latest('id')->first()->notes)
        ->toBe('Alamat lama, tidak terstruktur');
});

test('an empty address blocks the checkout instead of ordering without a location', function (): void {
    // `required` pada field inilah yang mencegah order tanpa alamat. Kalau aturan
    // ini dilepas, order tetap dibuat dan `notes` kosong -- tim dekorasi tidak
    // pernah tahu acara-nya di mana.
    $product = Product::factory()->create(['stock' => 5]);
    $paymentMethod = PaymentMethod::create(['name' => 'Transfer Bank', 'type' => 'bank_transfer', 'code' => 'bca', 'fee' => 0, 'is_active' => true]);

    Livewire::test(CheckoutProduct::class, ['record' => $product->getKey()])
        ->fillForm([
            'booking_date' => now()->addDays(7)->toDateString(),
            'booking_time' => '10:00',
            'quantity' => 1,
            'customer_name' => 'Rangga Wicaksono',
            'whatsapp' => '+628123456789',
            'payment_method_id' => $paymentMethod->id,
        ])
        ->set('data.event_address', null)
        ->call('submit')
        ->assertHasFormErrors(['event_address']);

    expect(Order::query()->count())->toBe(0)
        // Stok juga tidak boleh terpakai untuk order yang gagal.
        ->and($product->fresh()->stock)->toBe(5);
});

/*
 * ------------------------------------------------------------ checkout render
 */

test('the product checkout renders the address field with an edit button and modal', function (): void {
    $product = Product::factory()->create(['stock' => 5]);
    $paymentMethod = PaymentMethod::create(['name' => 'Transfer Bank', 'type' => 'bank_transfer', 'code' => 'bca', 'fee' => 0, 'is_active' => true]);

    $html = $this->get(ProductResource::getUrl('checkout', ['record' => $product], panel: 'user'))
        ->assertOk()
        ->getContent();

    // Field-nya ada, dengan modal dan tombol Edit sesuai gambar acuan.
    expect($html)
        ->toContain('fi-checkout-address', escape: false)
        ->toContain('data-checkout-address-area', escape: false)
        ->toContain('data-checkout-address-street', escape: false)
        ->toContain('data-checkout-address-detail', escape: false)
        ->toContain('data-checkout-address-map', escape: false)
        ->toContain(e(__('Ubah Alamat')))
        ->toContain(e(__('Edit')))
        ->toContain(e(__('Nanti Saja')))
        ->toContain(e(__('Gunakan Lokasi Saya')))
        // Penanda "Rumah / Kantor" dari gambar acuan.
        ->toContain(e(__('Rumah')))
        ->toContain(e(__('Kantor')))
        // Dan field lama tidak lagi ada di wizard.
        ->not->toContain('data.checkout_notes', escape: false);
});

test('the package checkout renders the same address field', function (): void {
    $package = Package::factory()->create(['stock' => 5]);

    $html = $this->get(PackageResource::getUrl('checkout', ['record' => $package], panel: 'user'))
        ->assertOk()
        ->getContent();

    // Dua halaman checkout tidak boleh berbeda perlakuan: keduanya memakai
    // method yang sama dari Resource masing-masing, tapi kalau salah satu
    // lupa menukar field, checkout paket akan diam-diam kehilangan koordinat.
    expect($html)->toContain('fi-checkout-address', escape: false)
        ->toContain(e(__('Gunakan Lokasi Saya')));
});

test('the maps script is only requested when an api key is configured', function (): void {
    // Tanpa key,autocomplete dan peta dinonaktifkan -- tapi GPS, ketik manual,
    // dan tombol simpan tetap harus ada. Meminta script Google tanpa key hanya
    // menambah request yang pasti gagal.
    config(['services.google.maps_key' => '']);

    $product = Product::factory()->create(['stock' => 5]);

    $html = $this->get(ProductResource::getUrl('checkout', ['record' => $product], panel: 'user'))
        ->assertOk()
        ->getContent();

    expect($html)
        ->not->toContain('checkout-address.js', escape: false)
        // Peringatan degradation-nya tetap muncul, dan kontrol manual tetap ada.
        ->toContain(e(__('Pencarian alamat otomatis belum aktif (GOOGLE_MAPS_API_KEY kosong). Ketik manual saja.')))
        ->toContain('data-checkout-address-street', escape: false)
        ->toContain(e(__('Gunakan Lokasi Saya')));
});

test('the field reports the configured maps key to the view', function (): void {
    config(['services.google.maps_key' => 'test-key-123']);

    expect(CheckoutAddress::make('event_address')->hasMapsKey())->toBeTrue()
        ->and(CheckoutAddress::make('event_address')->getMapsKey())->toBe('test-key-123')
        // Nilai per pemakaian mengalahkan config.
        ->and(CheckoutAddress::make('event_address')->mapsKey('override')->getMapsKey())->toBe('override');

    config(['services.google.maps_key' => '']);

    expect(CheckoutAddress::make('event_address')->hasMapsKey())->toBeFalse()
        ->and(CheckoutAddress::make('event_address')->getMapsKey())->toBe('');
});
