<?php

/**
 * Halaman "Ulasan Saya" (/reviews).
 *
 * Dua hal yang dikunci di sini:
 *
 *   1. Empty state punya SATU aksi: ke katalog. Dulu ada dua tombol dan
 *      keduanya menuju ke URL yang sama persis -- jadi yang kedua bukan
 *      pintu baru, cuma tombol yang lebih besar.
 *
 *   2. Aksi "Tulis Ulasan" adalah TAUTAN ke halaman CreateReviews, bukan
 *      MODAL. Dulu aksi itu membuka modal berisi dropdown katalog, lalu
 *      me-redirect ke CreateReviews. Sekarang: klik -> langsung ke halaman
 *      CreateReviews (mode "pilih katalog", route
 *      /flowerdecorationscatalog/reviews) -> pilih katalog -> formnya terbuka
 *      di halaman CreateReviews milik katalog itu (route /{record}/reviews).
 *
 * Kenapa tidak modal: user harusnya mendarat langsung di halaman tulis
 * ulasan. ModalDropdown cuma memindahkan masalahnya (membuka panel kecil
 * berisi daftar katalog) ke tempat lain.
 *
 * Katalog yang bisa dipilih: katalog aktif yang BELUM diulas user ini.
 * Katalog yang sudah pernah dipesan didahulukan, tapi TIDAK jadi syarat --
 * tombol yang tidak bisa dipakai sama sekali (daftar kosong) lebih buruk
 * daripada bolehnya mengulas katalog yang belum beli.
 */

use App\Enums\OrderPaymentStatus\OrderPaymentStatus;
use App\Enums\OrderStatus\OrderStatus;
use App\Filament\Welcome\Resources\CatalogResource\CatalogResource;
use App\Filament\Welcome\Resources\CatalogResource\Pages\CreateReviews\CreateReviews;
use App\Filament\Welcome\Resources\OrderResource\Pages\ManageOrders\ManageOrders;
use App\Filament\Welcome\Resources\ReviewResource\ReviewResource;
use App\Filament\Welcome\Resources\ReviewResource\Pages\ManageReviews\ManageReviews;
use App\Models\Catalog\Catalog;
use App\Models\Order\Order;
use App\Models\Review\Review;
use App\Models\User\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'customer']);

    Filament::setCurrentPanel(Filament::getPanel('welcome'));

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('customer');

    actingAs($this->user, 'web');
});

/**
 * Pesanan untuk satu katalog -- kandidat yang paling mungkin mau diulas.
 */
function makeReviewOrder(User $user, Catalog $catalog): Order
{
    return Order::create([
        'user_id'        => $user->id,
        'catalog_id'     => $catalog->id,
        'order_number'   => 'ORD-'.str_pad((string) $catalog->id, 6, '0', STR_PAD_LEFT),
        'status'         => OrderStatus::PENDING,
        'payment_status' => OrderPaymentStatus::UNPAID,
        'total_price'    => 100000,
        'booking_date'   => now()->addMonth(),
    ]);
}

it('halaman ulasan punya satu saja aksi di empty state: ke katalog', function (): void {
    $html = Livewire::test(ManageReviews::class)->html();

    // Aksi ke katalog: ada.
    expect($html)->toContain(__('Belanja Bunga'));

    // Duplikatnya dihapus: keduanya menuju ke URL yang sama.
    expect($html)->not->toContain(__('Pesan Paket Dekorasi'));
});

it('empty state mengarah ke halaman katalog', function (): void {
    $html = Livewire::test(ManageReviews::class)->html();

    expect($html)->toContain(CatalogResource::getUrl());
});

it('aksi tulis ulasan ada dan arahnya ke halaman CreateReviews', function (): void {
    $catalog = Catalog::factory()->create(['name' => 'Paket Vintage', 'is_active' => true]);
    makeReviewOrder($this->user, $catalog);

    $component = Livewire::test(ManageReviews::class);

    $component->assertTableActionExists('write_review');

    // AKSINYA ADALAH TAUTAN ke halaman CreateReviews (mode "pilih katalog"),
    // bukan action yang membuka modal.
    $component->assertTableActionHasUrl('write_review', CatalogResource::getUrl('write-review'));
});

it('aksi tulis ulasan tidak membuka modal', function (): void {
    $catalog = Catalog::factory()->create(['is_active' => true]);
    makeReviewOrder($this->user, $catalog);

    $html = Livewire::test(ManageReviews::class)->html();

    // Dulu modalnya berisi form dengan select `catalog_id`. Tidak ada form
    // seperti itu lagi -- tidak ada markup modal yang dirender.
    expect($html)->not->toContain('catalog_id');

    // Tombolnya tetapendered sebagai tautan ke halaman CreateReviews.
    expect($html)->toContain(CatalogResource::getUrl('write-review'));
});

it('halaman pilih katalog dilayani oleh kelas CreateReviews yang sama', function (): void {
    // Dua route, satu kelas:
    //   /reviews          -> mode "pilih katalog"
    //   /{record}/reviews -> mode form
    //
    // Kalau mode "pilih katalog" punya kelas sendiri, tombol "Tulis Ulasan"
    // tidak akan mengarah ke halaman CreateReviews.
    expect(CatalogResource::getPages()['write-review']->getPage())->toBe(CreateReviews::class)
        ->and(CatalogResource::getPages()['create-reviews']->getPage())->toBe(CreateReviews::class);
});

it('halaman pilih katalog bisa dibuka dan menampilkan katalog yang bisa diulas', function (): void {
    $catalog = Catalog::factory()->create(['is_active' => true]);
    makeReviewOrder($this->user, $catalog);

    $this->get(CatalogResource::getUrl('write-review'))
        ->assertOk()
        ->assertSee(__('Tulis Ulasan'))
        // Namanya dibaca dari `name_translations` (accessor `name`), bukan
        // dari kolom mentah -- makanya yang dipakai `$catalog->name`.
        ->assertSee($catalog->name);
});

it('halaman pilih katalog menautkan ke halaman CreateReviews milik katalog itu', function (): void {
    $catalog = Catalog::factory()->create(['is_active' => true]);
    makeReviewOrder($this->user, $catalog);

    $this->get(CatalogResource::getUrl('write-review'))
        ->assertOk()
        ->assertSee(CatalogResource::getUrl('create-reviews', ['record' => $catalog->id]));
});

it('halaman CreateReviews tanpa record menampilkan daftar katalog, bukan form', function (): void {
    $catalog = Catalog::factory()->create(['is_active' => true]);
    makeReviewOrder($this->user, $catalog);

    $html = Livewire::test(CreateReviews::class)->html();

    // Mode pilih katalog: daftar katalog, bukan form ulasan.
    // Namanya di-escape di HTML (mis. "Peony & Hydrangea" -> "&amp;").
    expect($html)->toContain(e($catalog->name))
        ->and($html)->not->toContain(__('Komentar Anda'));
});

it('halaman CreateReviews milik katalog itu bisa dibuka', function (): void {
    $catalog = Catalog::factory()->create(['is_active' => true]);
    makeReviewOrder($this->user, $catalog);

    $this->get(CatalogResource::getUrl('create-reviews', ['record' => $catalog->id]))
        ->assertOk()
        ->assertSee(__('Tulis Ulasan'))
        // Mode form: field ulasan ada.
        ->assertSee(__('Komentar Anda'));
});

it('kartu di halaman pilih katalog memakai grid katalog yang sama', function (): void {
    $catalog = Catalog::factory()->create(['is_active' => true]);
    makeReviewOrder($this->user, $catalog);

    $html = Livewire::test(CreateReviews::class)->html();

    // Komponen grid katalog yang SAMA dengan halaman katalog: kelas
    // `catalog-grid` + `catalog-card` -- bukan kartu custom yang lebih besar.
    expect($html)->toContain('catalog-grid')
        ->toContain('catalog-card')
        // Isi kartunya LENGKAP seperti katalog: nama, harga, rating, stok.
        ->toContain('catalog-name')
        ->toContain('catalog-price')
        ->toContain('catalog-stock');
});

it('pencarian di halaman pilih katalog menyaring daftar katalog', function (): void {
    $alpha = Catalog::factory()->create([
        'is_active' => true,
        'name_translations' => ['id' => 'Katalog Alfa', 'en' => 'Catalog Alfa'],
    ]);
    $beta = Catalog::factory()->create([
        'is_active' => true,
        'name_translations' => ['id' => 'Katalog Beta', 'en' => 'Catalog Beta'],
    ]);

    makeReviewOrder($this->user, $alpha);
    makeReviewOrder($this->user, $beta);

    $html = Livewire::test(CreateReviews::class)
        ->set('catalogSearch', 'Alfa')
        ->html();

    expect($html)->toContain(e('Katalog Alfa'))
        ->and($html)->not->toContain(e('Katalog Beta'));
});

it('tamu yang membuka halaman pilih katalog di panel welcome dilempar ke login', function (): void {
    // Tanpa akun: panel welcome punya path publik, jadi mount()-page yang
    // harus melempar tamu (ulasan butuh user_id).
    auth()->logout();

    Livewire::test(CreateReviews::class)
        ->assertRedirect(route(\App\Http\Middleware\AuthenticateWelcome\AuthenticateWelcome::LOGIN_ROUTE));
});

it('katalog yang belum pernah dipesan tetap bisa dipilih', function (): void {
    // Tombol yang tidak bisa dipakai sama sekali (select kosong) lebih buruk
    // daripada bolehnya mengulas katalog yang belum dibeli.
    $notOrdered = Catalog::factory()->create(['is_active' => true]);

    expect(ReviewResource::reviewableCatalogOptions())->toHaveKey($notOrdered->id);
});

it('katalog yang sudah diulas tidak ditawarkan lagi', function (): void {
    $catalog = Catalog::factory()->create(['is_active' => true]);
    makeReviewOrder($this->user, $catalog);

    Review::create([
        'user_id'    => $this->user->id,
        'catalog_id' => $catalog->id,
        'rating'     => 5,
        'comment'    => 'Bagus banget.',
    ]);

    expect(ReviewResource::reviewableCatalogOptions())->not->toHaveKey($catalog->id);
});

it('katalog non-aktif tidak ditawarkan', function (): void {
    $inactive = Catalog::factory()->create(['is_active' => false]);

    expect(ReviewResource::reviewableCatalogOptions())->not->toHaveKey($inactive->id);
});

it('aksi tulis ulasan disembunyikan saat tidak ada katalog yang bisa diulas', function (): void {
    // Semua katalog sudah diulas -> tidak ada yang bisa diulas, jadi
    // tombolnya tidak boleh muncul (membuka halaman kosong).
    $catalog = Catalog::factory()->create(['is_active' => true]);
    makeReviewOrder($this->user, $catalog);

    Review::create([
        'user_id'    => $this->user->id,
        'catalog_id' => $catalog->id,
        'rating'     => 5,
        'comment'    => 'Bagus banget.',
    ]);

    expect(ReviewResource::reviewableCatalogOptions())->toBe([]);

    Livewire::test(ManageReviews::class)
        ->assertTableActionHidden('write_review');
});

it('tombol tulis ulasan di daftar pesanan juga menuju ke halaman CreateReviews', function (): void {
    // Dulu action ini membuka MODAL dan membuat baris reviewnya sendiri di
    // halaman pesanan. Sekarang semua ulasan dibuat di `CreateReviews`.
    $catalog = Catalog::factory()->create(['is_active' => true]);
    $order = makeReviewOrder($this->user, $catalog);
    $order->update(['status' => OrderStatus::COMPLETED]);

    $html = Livewire::test(ManageOrders::class)->html();

    expect($html)->toContain(CatalogResource::getUrl('create-reviews', ['record' => $catalog->id]));
});
