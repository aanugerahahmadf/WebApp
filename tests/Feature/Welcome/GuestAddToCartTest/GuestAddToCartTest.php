<?php

/**
 * Alur guest Add to Cart di katalog Welcome (Package & Product):
 *
 * - Klik Add to Cart SELALU memunculkan form qty (termasuk untuk guest).
 * - Untuk guest, tombol Submit Livewire disembunyikan dan diganti link
 *   biasa ke halaman login, sehingga tidak ada POST /livewire/update
 *   dan error 419 tidak mungkin terjadi.
 * - Guard sisi server di action() tetap ada sebagai pengaman:
 *   guest yang memanggil action langsung diarahkan ke login (full reload
 *   antar panel, navigate: false) dan tidak ada baris cart yang dibuat.
 * - User login memakai Submit normal dan cart tersimpan.
 *
 * URL detail diambil via Resource::getUrl() agar kebal terhadap
 * perubahan slug resource.
 */

use App\Filament\Welcome\Resources\PackageResource\PackageResource as WelcomePackageResource;
use App\Filament\Welcome\Resources\PackageResource\Pages\ViewPackage\ViewPackage as WelcomeViewPackage;
use App\Filament\Welcome\Resources\ProductResource\Pages\ViewProduct\ViewProduct as WelcomeViewProduct;
use App\Filament\Welcome\Resources\ProductResource\ProductResource as WelcomeProductResource;
use App\Http\Middleware\AuthenticateWelcome\AuthenticateWelcome;
use App\Models\Cart\Cart;
use App\Models\Package\Package;
use App\Models\Product\Product;
use App\Models\User\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'customer']);

    $this->customer = User::factory()->create();
    $this->customer->assignRole('customer');

    Filament::setCurrentPanel(Filament::getPanel('welcome'));
});

it('guest bisa membuka detail paket dan produk tanpa error', function (): void {
    $package = Package::factory()->create(['stock' => 10]);
    $product = Product::factory()->create(['stock' => 10]);

    $this->get(WelcomePackageResource::getUrl('view', ['record' => $package->getKey()]))->assertOk();
    $this->get(WelcomeProductResource::getUrl('view', ['record' => $product->getKey()]))->assertOk();
});

it('guard server mengarahkan guest ke login dan menyimpan halaman tujuan', function (): void {
    $intended = WelcomePackageResource::getUrl('view', ['record' => 99]);

    $redirect = WelcomePackageResource::redirectGuestToLogin($intended);

    expect($redirect)->not->toBeNull()
        ->and($redirect->getTargetUrl())->toBe(route(AuthenticateWelcome::LOGIN_ROUTE))
        ->and(route(AuthenticateWelcome::LOGIN_ROUTE))->toContain('/user/auth')
        ->and(session()->get('url.intended'))->toBe($intended);
});

it('guard server membiarkan user login lewat (tidak redirect)', function (): void {
    actingAs($this->customer, 'web');

    expect(WelcomePackageResource::redirectGuestToLogin('/welcome/whatever'))->toBeNull();
});

it('modal guest tidak punya tombol Submit Livewire, adanya link biasa ke login', function (): void {
    $package = Package::factory()->create(['stock' => 10]);

    $test = Livewire::test(WelcomeViewPackage::class, ['record' => $package->getKey()])
        ->mountInfolistAction('.add_to_cart_detailAction', 'add_to_cart_detail')
        ->assertSee(route(AuthenticateWelcome::LOGIN_ROUTE), escape: false);

    // Submit Livewire disembunyikan untuk guest: tidak ada POST
    // /livewire/update sehingga 419 tidak mungkin terjadi.
    expect($test->instance()->getMountedInfolistAction()->getModalSubmitAction())->toBeNull();

    expect(Cart::count())->toBe(0);
});

it('user login bisa memasukkan paket ke keranjang lewat form qty', function (): void {
    actingAs($this->customer, 'web');

    $package = Package::factory()->create(['stock' => 10]);

    Livewire::actingAs($this->customer)
        ->test(WelcomeViewPackage::class, ['record' => $package->getKey()])
        ->callInfolistAction('.add_to_cart_detailAction', 'add_to_cart_detail', ['quantity' => 2])
        ->assertHasNoInfolistActionErrors();

    // Tambah 2 ke keranjang kosong harus menghasilkan 2, bukan 3.
    // Sebelumnya kode memakai updateOrCreate() + DB::raw('quantity + N'): pada
    // cabang INSERT, MySQL menyelesaikan `quantity` ke DEFAULT kolom (1), jadi
    // 1 + 2 = 3 — padahal notifikasi berbunyi "menambahkan 2 paket". SQLite
    // menolak ekspresi itu outright, jadi perilakunya tidak portabel sama sekali.
    $this->assertDatabaseHas('carts', [
        'user_id' => $this->customer->id,
        'package_id' => $package->id,
        'quantity' => 2,
    ]);
});

it('user login bisa memasukkan produk ke keranjang lewat form qty', function (): void {
    actingAs($this->customer, 'web');

    $product = Product::factory()->create(['stock' => 10]);

    Livewire::actingAs($this->customer)
        ->test(WelcomeViewProduct::class, ['record' => $product->getKey()])
        ->callInfolistAction('.add_to_cart_detailAction', 'add_to_cart_detail', ['quantity' => 3])
        ->assertHasNoInfolistActionErrors();

    // Tambah 3 ke keranjang kosong harus menghasilkan 3. Lihat catatan pada
    // test paket di atas untuk akar bug DB::raw('quantity + N').
    $this->assertDatabaseHas('carts', [
        'user_id' => $this->customer->id,
        'product_id' => $product->id,
        'quantity' => 3,
    ]);
});

it('menambah item yang sama dua kali akan menjumlahkan, bukan menimpa', function (): void {
    actingAs($this->customer, 'web');

    $product = Product::factory()->create(['stock' => 10]);

    // 2 + 3 = 5, dan tetap satu baris keranjang saja.
    Livewire::actingAs($this->customer)
        ->test(WelcomeViewProduct::class, ['record' => $product->getKey()])
        ->callInfolistAction('.add_to_cart_detailAction', 'add_to_cart_detail', ['quantity' => 2])
        ->callInfolistAction('.add_to_cart_detailAction', 'add_to_cart_detail', ['quantity' => 3]);

    $this->assertDatabaseCount('carts', 1);
    $this->assertDatabaseHas('carts', [
        'user_id' => $this->customer->id,
        'product_id' => $product->id,
        'quantity' => 5,
    ]);
});
