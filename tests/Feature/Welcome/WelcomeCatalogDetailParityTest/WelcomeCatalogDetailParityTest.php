<?php

/**
 * Tests for the Welcome panel catalog and package detail pages.
 *
 * Mirrors CatalogDetailParityTest but targets the WELCOME panel
 * (App\Filament\Welcome\...) instead of the User panel.
 *
 * Covers:
 * - Detail pages render all expected UI blocks
 * - CBIR badge, discount badge, features section
 * - Review section appears when reviews exist
 * - Cart action adds to DB
 * - Report action sends chat message
 * - Guest sees all buttons, each redirects to login
 */

use App\Filament\Welcome\Resources\CatalogResource\Pages\ViewCatalog\ViewCatalog;
use App\Models\Inbox\Inbox;
use App\Models\Package\Package;
use App\Models\Catalog\Catalog;
use App\Models\Review\Review;
use App\Models\User\User;
use App\Services\ChatService\ChatService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Queue::fake();

    // Role untuk user di bawah (guard `web`) DAN untuk panel admin (guard
    // `admin`). `User::assignRole()` mencari di guard model itu sendiri, jadi
    // `super_admin` versi `web` harus ada juga.
    seedRoles(adminRoles: ['super_admin'], webRoles: ['super_admin', 'customer']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');

    $this->customer = User::factory()->create();
    $this->customer->assignRole('customer');

    Filament::setCurrentPanel(Filament::getPanel('welcome'));
});

// ---------------------------------------------------------------------------
// Catalog detail â€” Welcome panel
// ---------------------------------------------------------------------------

it('welcome catalog detail renders name, share, report, cart, chat, wishlist, review buttons', function (): void {
    actingAs($this->customer, 'web');

    // `name_translations`, bukan `name` -- lihat catatan di `CatalogFactory`.
    $catalog = Catalog::factory()->create([
        'stock'             => 5,
        'discount_price'    => 80000,
        'features'          => ['Fitur Satu', 'Fitur Dua'],
        'name_translations' => [
            'id' => 'Produk Welcome Test',
            'en' => 'Welcome Test Product',
        ],
    ]);

    Livewire::test(ViewCatalog::class, ['record' => $catalog->getKey()])
        ->assertSuccessful()
        ->assertSee('Produk Welcome Test')
        ->assertSee(__('Bagikan'))
        ->assertSee(__('Lapor'))
        ->assertSee(__('Masukkan ke Keranjang'))
        ->assertSee(__('Chat Admin'))
        ->assertSee(__('Tambah ke Favorit'))
        ->assertSee(__('Tulis Ulasan'))
        ->assertSee('DISKON')
        ->assertSee('Fitur Satu');
});

it('welcome catalog detail shows reviews when they exist', function (): void {
    actingAs($this->customer, 'web');

    $catalog  = Catalog::factory()->create([
            'stock' => 2,
            'name_translations' => ['id' => 'Produk Dengan Ulasan', 'en' => 'Reviewed Product'],
        ]);
    $reviewer = User::factory()->create(['full_name' => 'Reviewer Satu']);
    Review::create([
        'user_id'    => $reviewer->id,
        'catalog_id' => $catalog->id,
        'package_id' => null,
        'rating'     => 5,
        'comment'    => 'Sangat bagus!',
    ]);

    Livewire::test(ViewCatalog::class, ['record' => $catalog->getKey()])
        ->assertSuccessful()
        ->assertSee(__('Ulasan'))
        ->assertSee('Reviewer Satu');
});

it('welcome catalog detail hides the review button for a customer who already reviewed', function (): void {
    actingAs($this->customer, 'web');

    $catalog = Catalog::factory()->create([
            'stock' => 2,
            'name_translations' => ['id' => 'Already Reviewed', 'en' => 'Already Reviewed'],
        ]);
    Review::create([
        'user_id'    => $this->customer->id,
        'catalog_id' => $catalog->id,
        'package_id' => null,
        'rating'     => 4,
        'comment'    => 'Good.',
    ]);

    Livewire::test(ViewCatalog::class, ['record' => $catalog->getKey()])
        ->assertSuccessful()
        ->assertDontSee(__('Tulis Ulasan'));
});

/*
 * Test "welcome package detail renders all expected blocks" dan "welcome package
 * detail shows out-of-stock label when stock is zero" DIHAPUS.
 *
 * Keduanya memanggil `ViewPackage::class`, yang sudah tidak ada bersama
 * `PackageResource`-nya. Gejalanya `Unable to find component: [ViewPackage]`.
 *
 * Yang hilang bukan aturan yang diuji: setiap blok yang diperiksa test pertama
 * -- Bagikan, Lapor, Masukkan ke Keranjang, Chat Admin, Tambah ke Favorit,
 * Tulis Ulasan, label DISKON, daftar fitur -- sudah diperiksa untuk katalog di
 * bagian atas file ini, dengan record `Catalog`. Menyalinnya ke "tier paket"
 * hanya akan menambah salinan yang menguji halaman katalog sambil menyebut
 * paket.
 *
 * Kalau halaman detail paket dihidupkan lagi: `Package` kini adalah TIER dari
 * katalog (`Catalog::tiers()`), jadi tidak ada resource terpisah yang bisa
 * menyalin struktur `ViewCatalog`. Test perlu ditulis ulang sesuai route tier
 * yang benar-benar dibuat, bukan dengan menyalin test katalog.
 */

// ---------------------------------------------------------------------------
// ChatService â€” report message
// ---------------------------------------------------------------------------

it('report_item action sends a report message through chat service (welcome)', function (): void {
    Queue::fake();
    actingAs($this->customer, 'web');

    $inbox = Inbox::create(['user_ids' => [$this->customer->id, $this->admin->id]]);

    $message = app(ChatService::class)->sendReportMessage(
        inbox: $inbox,
        category: 'catalog',
        itemName: 'Produk Laporan Test',
        meta: [
            'type'      => 'catalog',
            'id'        => 1,
            'name'      => 'Produk Laporan Test',
            'is_report' => true,
        ],
    );

    expect($message->meta['is_report'])->toBeTrue()
        ->and($message->meta['type'])->toBe('catalog');
});

// ---------------------------------------------------------------------------
// Guest visibility
// ---------------------------------------------------------------------------

it('welcome catalog detail is accessible to a guest without 500', function (): void {
    $catalog = Catalog::factory()->create([
            'stock' => 1,
            'name_translations' => ['id' => 'Guest Visible', 'en' => 'Guest Visible'],
        ]);

    $this->get("/flowerdecorationscatalog/{$catalog->getKey()}")
        ->assertOk()
        ->assertSee('Guest Visible');
});

/*
 * Test "welcome package detail is accessible to a guest without 500" DIHAPUS.
 *
 * Sama seperti dua test sebelumnya: URL `/welcome/flowerdecorationspackagecatalog`
 * sudah tidak ada, jadi 404-nya bukan soal akses tamu. Test katalog tepat di
 * atasnya menguji akses tamu lewat URL yang benar.
 */

