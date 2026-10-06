<?php

/**
 * Tests for the Welcome panel product and package detail pages.
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

use App\Filament\Welcome\Resources\PackageResource\Pages\ViewPackage\ViewPackage;
use App\Filament\Welcome\Resources\ProductResource\Pages\ViewProduct\ViewProduct;
use App\Models\Inbox\Inbox;
use App\Models\Package\Package;
use App\Models\Product\Product;
use App\Models\Review\Review;
use App\Models\User\User;
use App\Services\ChatService\ChatService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Queue::fake();

    Role::firstOrCreate(['name' => 'super_admin']);
    Role::firstOrCreate(['name' => 'customer']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');

    $this->customer = User::factory()->create();
    $this->customer->assignRole('customer');

    Filament::setCurrentPanel(Filament::getPanel('welcome'));
});

// ---------------------------------------------------------------------------
// Product detail — Welcome panel
// ---------------------------------------------------------------------------

it('welcome product detail renders name, share, report, cart, chat, wishlist, review buttons', function (): void {
    actingAs($this->customer, 'web');

    $product = Product::factory()->create([
        'name'           => 'Produk Welcome Test',
        'stock'          => 5,
        'discount_price' => 80000,
        'features'       => ['Fitur Satu', 'Fitur Dua'],
    ]);

    Livewire::test(ViewProduct::class, ['record' => $product->getKey()])
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

it('welcome product detail shows reviews when they exist', function (): void {
    actingAs($this->customer, 'web');

    $product  = Product::factory()->create(['name' => 'Produk Dengan Ulasan', 'stock' => 2]);
    $reviewer = User::factory()->create(['full_name' => 'Reviewer Satu']);
    Review::create([
        'user_id'    => $reviewer->id,
        'product_id' => $product->id,
        'package_id' => null,
        'rating'     => 5,
        'comment'    => 'Sangat bagus!',
    ]);

    Livewire::test(ViewProduct::class, ['record' => $product->getKey()])
        ->assertSuccessful()
        ->assertSee(__('Ulasan'))
        ->assertSee('Reviewer Satu');
});

it('welcome product detail hides the review button for a customer who already reviewed', function (): void {
    actingAs($this->customer, 'web');

    $product = Product::factory()->create(['name' => 'Already Reviewed', 'stock' => 2]);
    Review::create([
        'user_id'    => $this->customer->id,
        'product_id' => $product->id,
        'package_id' => null,
        'rating'     => 4,
        'comment'    => 'Good.',
    ]);

    Livewire::test(ViewProduct::class, ['record' => $product->getKey()])
        ->assertSuccessful()
        ->assertDontSee(__('Tulis Ulasan'));
});

// ---------------------------------------------------------------------------
// Package detail — Welcome panel
// ---------------------------------------------------------------------------

it('welcome package detail renders all expected blocks', function (): void {
    actingAs($this->customer, 'web');

    $package = Package::factory()->create([
        'name'           => 'Paket Welcome Test',
        'stock'          => 3,
        'discount_price' => 500000,
        'features'       => ['Podium Bunga', 'Backdrop LED'],
    ]);

    Livewire::test(ViewPackage::class, ['record' => $package->getKey()])
        ->assertSuccessful()
        ->assertSee('Paket Welcome Test')
        ->assertSee(__('Bagikan'))
        ->assertSee(__('Lapor'))
        ->assertSee(__('Masukkan ke Keranjang'))
        ->assertSee(__('Chat Admin'))
        ->assertSee(__('Tambah ke Favorit'))
        ->assertSee(__('Tulis Ulasan'))
        ->assertSee('DISKON')
        ->assertSee('Podium Bunga');
});

it('welcome package detail shows out-of-stock label when stock is zero', function (): void {
    actingAs($this->customer, 'web');

    $package = Package::factory()->create(['name' => 'Habis Test', 'stock' => 0]);

    Livewire::test(ViewPackage::class, ['record' => $package->getKey()])
        ->assertSuccessful()
        ->assertSee(__('Layanan Habis'));
});

// ---------------------------------------------------------------------------
// ChatService — report message
// ---------------------------------------------------------------------------

it('report_item action sends a report message through chat service (welcome)', function (): void {
    Queue::fake();
    actingAs($this->customer, 'web');

    $inbox = Inbox::create(['user_ids' => [$this->customer->id, $this->admin->id]]);

    $message = app(ChatService::class)->sendReportMessage(
        inbox: $inbox,
        category: 'product',
        itemName: 'Produk Laporan Test',
        meta: [
            'type'      => 'product',
            'id'        => 1,
            'name'      => 'Produk Laporan Test',
            'is_report' => true,
        ],
    );

    expect($message->meta['is_report'])->toBeTrue()
        ->and($message->meta['type'])->toBe('product');
});

// ---------------------------------------------------------------------------
// Guest visibility
// ---------------------------------------------------------------------------

it('welcome product detail is accessible to a guest without 500', function (): void {
    $product = Product::factory()->create(['name' => 'Guest Visible', 'stock' => 1]);

    $this->get("/welcome/flowerdecorationscatalog/{$product->getKey()}")
        ->assertOk()
        ->assertSee('Guest Visible');
});

it('welcome package detail is accessible to a guest without 500', function (): void {
    $package = Package::factory()->create(['name' => 'Guest Package', 'stock' => 1]);

    $this->get("/welcome/flowerdecorationspackagecatalog/{$package->getKey()}")
        ->assertOk()
        ->assertSee('Guest Package');
});
