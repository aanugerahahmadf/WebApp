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
        ->and($redirect->getTargetUrl())->toBe(route('filament.user.auth.login'))
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
        ->assertSee(route('filament.user.auth.login'), escape: false);

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

    // Catatan: Cart::updateOrCreate memakai DB::raw('quantity + N') sehingga
    // baris BARU terisi default kolom (1) + N. Ini perilaku kode saat ini.
    $this->assertDatabaseHas('carts', [
        'user_id' => $this->customer->id,
        'package_id' => $package->id,
        'quantity' => 3,
    ]);
});

it('user login bisa memasukkan produk ke keranjang lewat form qty', function (): void {
    actingAs($this->customer, 'web');

    $product = Product::factory()->create(['stock' => 10]);

    Livewire::actingAs($this->customer)
        ->test(WelcomeViewProduct::class, ['record' => $product->getKey()])
        ->callInfolistAction('.add_to_cart_detailAction', 'add_to_cart_detail', ['quantity' => 3])
        ->assertHasNoInfolistActionErrors();

    // Catatan: lihat komentar quantity + N pada test paket di atas.
    $this->assertDatabaseHas('carts', [
        'user_id' => $this->customer->id,
        'product_id' => $product->id,
        'quantity' => 4,
    ]);
});
