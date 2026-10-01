<?php

/**
 * Tests for the "Chat Admin" button and "Add to Favorites" button
 * on the Welcome panel product and package detail pages.
 *
 * Covers:
 * - chat_admin works for a signed-in customer (redirects to messages)
 * - chat_admin works for a guest (GuestIdentity path)
 * - chat_admin shows an error notification when no admin exists
 * - wishlist_detail adds to wishlist for a signed-in customer
 * - wishlist_detail removes from wishlist when already saved
 * - wishlist_detail redirects guest to login
 * - No 419 / CSRF error on wishlist_detail (no modal form)
 */

use App\Filament\Welcome\Pages\MessagesPage\MessagesPage;
use App\Filament\Welcome\Resources\PackageResource\Pages\ViewPackage\ViewPackage;
use App\Filament\Welcome\Resources\ProductResource\Pages\ViewProduct\ViewProduct;
use App\Models\Inbox\Inbox;
use App\Models\Package\Package;
use App\Models\Product\Product;
use App\Models\User\User;
use App\Models\Wishlist\Wishlist;
use App\Services\GuestIdentity\GuestIdentity;
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

    $this->product = Product::factory()->create(['name' => 'Bunga Mawar Test', 'stock' => 5]);
    $this->package = Package::factory()->create(['name' => 'Paket Dekorasi Test', 'stock' => 3]);

    Filament::setCurrentPanel(Filament::getPanel('welcome'));
});

// ---------------------------------------------------------------------------
// chat_admin — Product
// ---------------------------------------------------------------------------

it('chat_admin on a product page redirects a signed-in customer to the messages inbox', function (): void {
    actingAs($this->customer, 'web');

    Livewire::test(ViewProduct::class, ['record' => $this->product->getKey()])
        ->callInfolistAction('.chat_adminAction', 'chat_admin')
        ->assertRedirect();

    // An inbox must have been created between the customer and the admin
    $this->assertDatabaseHas('fm_inboxes', []);
    $inbox = Inbox::latest()->first();
    expect($inbox->user_ids)->toContain($this->customer->id)
        ->and($inbox->user_ids)->toContain($this->admin->id);

    // A context message must have been written
    $this->assertDatabaseHas('fm_messages', [
        'inbox_id' => $inbox->id,
        'user_id'  => $this->customer->id,
    ]);
});

it('chat_admin on a package page redirects a signed-in customer to messages', function (): void {
    actingAs($this->customer, 'web');

    Livewire::test(ViewPackage::class, ['record' => $this->package->getKey()])
        ->callInfolistAction('.chat_adminAction', 'chat_admin')
        ->assertRedirect();

    $inbox = Inbox::latest()->first();
    expect($inbox)->not->toBeNull()
        ->and($inbox->user_ids)->toContain($this->customer->id);
});

it('chat_admin sends the product context card as a meta message', function (): void {
    actingAs($this->customer, 'web');

    Livewire::test(ViewProduct::class, ['record' => $this->product->getKey()])
        ->callInfolistAction('.chat_adminAction', 'chat_admin');

    $inbox   = Inbox::latest()->first();
    $message = $inbox?->messages()->latest()->first();

    expect($message)->not->toBeNull()
        ->and($message->meta['type'])->toBe('product')
        ->and($message->meta['id'])->toBe($this->product->id)
        ->and($message->meta['name'])->toBe('Bunga Mawar Test');
});

it('chat_admin sends the package context card as a meta message', function (): void {
    actingAs($this->customer, 'web');

    Livewire::test(ViewPackage::class, ['record' => $this->package->getKey()])
        ->callInfolistAction('.chat_adminAction', 'chat_admin');

    $inbox   = Inbox::latest()->first();
    $message = $inbox?->messages()->latest()->first();

    expect($message)->not->toBeNull()
        ->and($message->meta['type'])->toBe('package')
        ->and($message->meta['id'])->toBe($this->package->id);
});

it('chat_admin shows a warning when no admin user exists', function (): void {
    // Remove the admin so ChatService cannot find one
    $this->admin->delete();
    actingAs($this->customer, 'web');

    Livewire::test(ViewProduct::class, ['record' => $this->product->getKey()])
        ->callInfolistAction('.chat_adminAction', 'chat_admin')
        // No redirect — action returns early with a notification
        ->assertNotified();
});

// ---------------------------------------------------------------------------
// chat_admin — Guest (GuestIdentity path)
// ---------------------------------------------------------------------------

it('chat_admin works for a guest via GuestIdentity', function (): void {
    // No actingAs — purely anonymous request
    Livewire::test(ViewProduct::class, ['record' => $this->product->getKey()])
        ->callInfolistAction('.chat_adminAction', 'chat_admin')
        ->assertRedirect();

    // A guest user row should have been created
    $guestUser = app(GuestIdentity::class)->user();
    expect($guestUser)->not->toBeNull();

    // An inbox must exist
    $inbox = Inbox::latest()->first();
    expect($inbox)->not->toBeNull();
});

// ---------------------------------------------------------------------------
// wishlist_detail — Product
// ---------------------------------------------------------------------------

it('wishlist_detail adds the product to the wishlist for a signed-in customer', function (): void {
    actingAs($this->customer, 'web');

    Livewire::test(ViewProduct::class, ['record' => $this->product->getKey()])
        ->callInfolistAction('.wishlist_detailAction', 'wishlist_detail');

    $this->assertDatabaseHas('wishlists', [
        'user_id'    => $this->customer->id,
        'product_id' => $this->product->id,
    ]);
});

it('wishlist_detail removes the product from the wishlist when already saved', function (): void {
    actingAs($this->customer, 'web');

    // Pre-insert the wishlist row
    Wishlist::create([
        'user_id'    => $this->customer->id,
        'product_id' => $this->product->id,
    ]);

    Livewire::test(ViewProduct::class, ['record' => $this->product->getKey()])
        ->callInfolistAction('.wishlist_detailAction', 'wishlist_detail');

    $this->assertDatabaseMissing('wishlists', [
        'user_id'    => $this->customer->id,
        'product_id' => $this->product->id,
    ]);
});

// ---------------------------------------------------------------------------
// wishlist_detail — Package
// ---------------------------------------------------------------------------

it('wishlist_detail adds the package to the wishlist', function (): void {
    actingAs($this->customer, 'web');

    Livewire::test(ViewPackage::class, ['record' => $this->package->getKey()])
        ->callInfolistAction('.wishlist_detailAction', 'wishlist_detail');

    $this->assertDatabaseHas('wishlists', [
        'user_id'    => $this->customer->id,
        'package_id' => $this->package->id,
    ]);
});

it('wishlist_detail removes the package from the wishlist when already saved', function (): void {
    actingAs($this->customer, 'web');

    Wishlist::create([
        'user_id'    => $this->customer->id,
        'package_id' => $this->package->id,
    ]);

    Livewire::test(ViewPackage::class, ['record' => $this->package->getKey()])
        ->callInfolistAction('.wishlist_detailAction', 'wishlist_detail');

    $this->assertDatabaseMissing('wishlists', [
        'user_id'    => $this->customer->id,
        'package_id' => $this->package->id,
    ]);
});

// ---------------------------------------------------------------------------
// wishlist_detail — Guest redirect (no 419)
// ---------------------------------------------------------------------------

it('wishlist_detail redirects a guest to login without throwing 419', function (): void {
    // No actingAs — guest session
    Livewire::test(ViewProduct::class, ['record' => $this->product->getKey()])
        ->callInfolistAction('.wishlist_detailAction', 'wishlist_detail')
        ->assertRedirect(route('filament.user.auth.login'));

    // Wishlist row must NOT have been created
    $this->assertDatabaseMissing('wishlists', ['product_id' => $this->product->id]);
});

it('wishlist_detail for package redirects a guest to login', function (): void {
    Livewire::test(ViewPackage::class, ['record' => $this->package->getKey()])
        ->callInfolistAction('.wishlist_detailAction', 'wishlist_detail')
        ->assertRedirect(route('filament.user.auth.login'));

    $this->assertDatabaseMissing('wishlists', ['package_id' => $this->package->id]);
});

// ---------------------------------------------------------------------------
// Idempotency — double-click protection
// ---------------------------------------------------------------------------

it('double wishlist add only creates one row', function (): void {
    actingAs($this->customer, 'web');

    $component = Livewire::test(ViewProduct::class, ['record' => $this->product->getKey()]);
    $component->callInfolistAction('.wishlist_detailAction', 'wishlist_detail');
    // Toggle back off then on again
    $component->callInfolistAction('.wishlist_detailAction', 'wishlist_detail');
    $component->callInfolistAction('.wishlist_detailAction', 'wishlist_detail');

    expect(Wishlist::where('user_id', $this->customer->id)
        ->where('product_id', $this->product->id)
        ->count())->toBe(1);
});
