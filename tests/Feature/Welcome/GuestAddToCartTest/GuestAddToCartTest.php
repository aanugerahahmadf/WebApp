<?php

use App\Filament\Welcome\Resources\CatalogResource\CatalogResource as WelcomeCatalogResource;
use App\Filament\Welcome\Resources\CatalogResource\Pages\ViewCatalog\ViewCatalog as WelcomeViewCatalog;
use App\Http\Middleware\AuthenticateWelcome\AuthenticateWelcome;
use App\Models\Cart\Cart;
use App\Models\Catalog\Catalog;
use App\Models\User\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

/**
 * Alur guest Add to Cart di katalog Welcome:
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
 *
 * HANYA KATALOG, BUKAN PAKET.
 *
 * File ini dulu menguji keduanya lewat `PackageResource` +
 * `PackageResource\Pages\ViewCatalog\ViewCatalog`. Keduanya sudah tidak ada
 * sejak penataan ulang `Product` -> `Catalog`: `Welcome\Resources\PackageResource`
 * dihapus, dan `ViewCatalog` yang tersisa hanya punya
 * `protected static string $resource = CatalogResource::class`.
 *
 * Test paket DIHAPUS, bukan diarahkan ulang ke `WelcomeViewCatalog`. Alasannya
 * bukan hanya class-nya hilang: `ViewCatalog::resolveRecord()` mencari record
 * di tabel `catalogs`, jadi memberikan id paket akan menghasilkan 404 -- testnya
 * akan hijau sambil menguji katalog, bukan paket. Dan `carts.package_id` masih
 * ada di skema, jadi ada temptation untuk membuat record `Package` lalu
 * memaksa lewat; itu hanya memunculkan kegagalan yang lebih menyesatkan.
 *
 * Kalau alur add-to-cart paket dihidupkan lagi, yang perlu kembali bukan hanya
 * import: butuh resource-nya, halaman detailnya, dan keputusan sendiri soal
 * apakah `carts.package_id` masih relevan atau tabelnya ikut dibersihkan.
 */

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'customer']);

    $this->customer = User::factory()->create();
    $this->customer->assignRole('customer');

    Filament::setCurrentPanel(Filament::getPanel('welcome'));
});

it('guest bisa membuka detail katalog tanpa error', function (): void {
    $catalog = Catalog::factory()->create(['stock' => 10]);

    $this->get(WelcomeCatalogResource::getUrl('view', ['record' => $catalog->getKey()]))->assertOk();
});

it('guard server mengarahkan guest ke login dan menyimpan halaman tujuan', function (): void {
    $intended = WelcomeCatalogResource::getUrl('view', ['record' => 99]);

    $redirect = WelcomeCatalogResource::redirectGuestToLogin($intended);

    expect($redirect)->not->toBeNull()
        ->and($redirect->getTargetUrl())->toBe(route(AuthenticateWelcome::LOGIN_ROUTE))
        ->and(route(AuthenticateWelcome::LOGIN_ROUTE))->toContain('/auth')
        ->and(session()->get('url.intended'))->toBe($intended);
});

it('guard server membiarkan user login lewat (tidak redirect)', function (): void {
    actingAs($this->customer, 'web');

    expect(WelcomeCatalogResource::redirectGuestToLogin('/welcome/whatever'))->toBeNull();
});

it('modal guest tidak punya tombol Submit Livewire, adanya link biasa ke login', function (): void {
    $catalog = Catalog::factory()->create(['stock' => 10]);

    $test = Livewire::test(WelcomeViewCatalog::class, ['record' => $catalog->getKey()])
        ->mountInfolistAction('.add_to_cart_detailAction', 'add_to_cart_detail')
        ->assertSee(route(AuthenticateWelcome::LOGIN_ROUTE), escape: false);

    // Submit Livewire disembunyikan untuk guest: tidak ada POST
    // /livewire/update sehingga 419 tidak mungkin terjadi.
    expect($test->instance()->getMountedInfolistAction()->getModalSubmitAction())->toBeNull();

    expect(Cart::count())->toBe(0);
});

it('user login bisa memasukkan katalog ke keranjang lewat form qty', function (): void {
    actingAs($this->customer, 'web');

    $catalog = Catalog::factory()->create(['stock' => 10]);

    Livewire::actingAs($this->customer)
        ->test(WelcomeViewCatalog::class, ['record' => $catalog->getKey()])
        ->callInfolistAction('.add_to_cart_detailAction', 'add_to_cart_detail', ['quantity' => 3])
        ->assertHasNoInfolistActionErrors();

    // Tambah 3 ke keranjang kosong harus menghasilkan 3, bukan 4. Test ini
    // menjaga akar bug `updateOrCreate()` + `DB::raw('quantity + N')`: pada
    // cabang INSERT, MySQL menyelesaikan `quantity` ke DEFAULT kolom (1), jadi
    // 1 + 3 = 4 -- padahal notifikasi berbunyi "menambahkan 3". SQLite menolak
    // ekspresi itu outright, jadi perilakunya tidak portabel sama sekali.
    $this->assertDatabaseHas('carts', [
        'user_id' => $this->customer->id,
        'catalog_id' => $catalog->id,
        'quantity' => 3,
    ]);
});

it('menambah item yang sama dua kali akan menjumlahkan, bukan menimpa', function (): void {
    actingAs($this->customer, 'web');

    $catalog = Catalog::factory()->create(['stock' => 10]);

    // 2 + 3 = 5, dan tetap satu baris keranjang saja.
    Livewire::actingAs($this->customer)
        ->test(WelcomeViewCatalog::class, ['record' => $catalog->getKey()])
        ->callInfolistAction('.add_to_cart_detailAction', 'add_to_cart_detail', ['quantity' => 2])
        ->callInfolistAction('.add_to_cart_detailAction', 'add_to_cart_detail', ['quantity' => 3]);

    $this->assertDatabaseCount('carts', 1);
    $this->assertDatabaseHas('carts', [
        'user_id' => $this->customer->id,
        'catalog_id' => $catalog->id,
        'quantity' => 5,
    ]);
});

