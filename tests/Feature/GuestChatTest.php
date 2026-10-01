<?php

/**
 * Tests for guest chat via GuestIdentity on the Welcome panel.
 *
 * The old GuestChatTest hit /api/messages/guest/* JSON endpoints.
 * Those endpoints no longer exist. Guest chat now works through:
 *   1. The "Chat Admin" infolist action on product/package detail pages
 *      (uses GuestIdentity to get/create a guest user row, then creates
 *       an Inbox and redirects to /welcome/messages/{id})
 *   2. The MessagesPage which verifies the guest is in inbox.user_ids
 *
 * These tests pin down the GuestIdentity + ChatService integration on
 * the Welcome panel path.
 */

use App\Filament\Welcome\Resources\PackageResource\Pages\ViewPackage\ViewPackage;
use App\Filament\Welcome\Resources\ProductResource\Pages\ViewProduct\ViewProduct;
use App\Models\Inbox\Inbox;
use App\Models\Package\Package;
use App\Models\Product\Product;
use App\Models\User\User;
use App\Services\ChatService\ChatService;
use App\Services\GuestIdentity\GuestIdentity;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Queue::fake();

    Role::firstOrCreate(['name' => 'super_admin']);
    Role::firstOrCreate(['name' => 'customer']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');

    $this->product = Product::factory()->create(['name' => 'Bunga Tamu', 'stock' => 5]);
    $this->package = Package::factory()->create(['name' => 'Paket Tamu', 'stock' => 3]);

    Filament::setCurrentPanel(Filament::getPanel('welcome'));
});

// ---------------------------------------------------------------------------
// GuestIdentity — user creation
// ---------------------------------------------------------------------------

it('GuestIdentity creates a guest user row on first call', function (): void {
    $guestIdentity = app(GuestIdentity::class);
    $user          = $guestIdentity->user();

    expect($user)->not->toBeNull()
        ->and($user->email)->toContain('@guest.local');

    $this->assertDatabaseHas('users', ['id' => $user->id]);
});

it('GuestIdentity returns the same user on subsequent calls in the same session', function (): void {
    $guestIdentity = app(GuestIdentity::class);

    $user1 = $guestIdentity->user();
    $user2 = $guestIdentity->user();

    expect($user1->id)->toBe($user2->id);
});

it('GuestIdentity id() matches the user id', function (): void {
    $guestIdentity = app(GuestIdentity::class);

    $user = $guestIdentity->user();
    $id   = $guestIdentity->id();

    expect($id)->toBe($user->id);
});

// ---------------------------------------------------------------------------
// Guest can start a conversation via Chat Admin
// ---------------------------------------------------------------------------

it('a guest can start a conversation by clicking Chat Admin on a product page', function (): void {
    // No actingAs — purely anonymous
    Livewire::test(ViewProduct::class, ['record' => $this->product->getKey()])
        ->callInfolistAction('.chat_adminAction', 'chat_admin')
        ->assertRedirect();

    // A guest row must exist
    $guestUser = app(GuestIdentity::class)->user();
    expect($guestUser)->not->toBeNull();

    // An inbox with the guest and admin must exist
    $inbox = Inbox::latest()->first();
    expect($inbox)->not->toBeNull()
        ->and($inbox->user_ids)->toContain($guestUser->id)
        ->and($inbox->user_ids)->toContain($this->admin->id);
});

it('a guest can start a conversation by clicking Chat Admin on a package page', function (): void {
    Livewire::test(ViewPackage::class, ['record' => $this->package->getKey()])
        ->callInfolistAction('.chat_adminAction', 'chat_admin')
        ->assertRedirect();

    $inbox = Inbox::latest()->first();
    expect($inbox)->not->toBeNull();
});

it('a second Chat Admin click from the same guest reuses the same inbox', function (): void {
    // First click
    Livewire::test(ViewProduct::class, ['record' => $this->product->getKey()])
        ->callInfolistAction('.chat_adminAction', 'chat_admin');

    $countAfterFirst = Inbox::count();

    // Second click (same guest session — GuestIdentity uses the same user row)
    Livewire::test(ViewProduct::class, ['record' => $this->product->getKey()])
        ->callInfolistAction('.chat_adminAction', 'chat_admin');

    // Inbox count must not have grown
    expect(Inbox::count())->toBe($countAfterFirst);
});

// ---------------------------------------------------------------------------
// Guest can read their own conversation via MessagesPage
// ---------------------------------------------------------------------------

it('a guest can access their own inbox on the Welcome messages page', function (): void {
    // Create the guest identity row
    $guestUser = app(GuestIdentity::class)->user();
    $inbox     = Inbox::create(['user_ids' => [$guestUser->id, $this->admin->id]]);

    // The guest is now "logged in" as the guest user row for this test
    actingAs($guestUser, 'web');

    get("/welcome/messages/{$inbox->id}")->assertOk();
});

it('a guest cannot access another guest\'s inbox', function (): void {
    $otherGuest = User::factory()->create(['email' => 'guest_other@guest.local']);
    $inbox      = Inbox::create(['user_ids' => [$otherGuest->id, $this->admin->id]]);

    $currentGuest = app(GuestIdentity::class)->user();
    actingAs($currentGuest, 'web');

    get("/welcome/messages/{$inbox->id}")->assertForbidden();
});

it('a guest cannot access a nonexistent inbox', function (): void {
    $guestUser = app(GuestIdentity::class)->user();
    actingAs($guestUser, 'web');

    get('/welcome/messages/999999')->assertNotFound();
});

// ---------------------------------------------------------------------------
// ChatService — getOrCreateInboxWithAdmin
// ---------------------------------------------------------------------------

it('ChatService creates a new inbox with the admin when none exists', function (): void {
    $guestUser = app(GuestIdentity::class)->user();

    $inbox = ChatService::getOrCreateInboxWithAdmin($guestUser->getKey());

    expect($inbox)->not->toBeNull()
        ->and($inbox->user_ids)->toContain($guestUser->id)
        ->and($inbox->user_ids)->toContain($this->admin->id);

    $this->assertDatabaseHas('fm_inboxes', ['id' => $inbox->id]);
});

it('ChatService returns the existing inbox on a second call for the same guest', function (): void {
    $guestUser = app(GuestIdentity::class)->user();

    $inbox1 = ChatService::getOrCreateInboxWithAdmin($guestUser->getKey());
    $inbox2 = ChatService::getOrCreateInboxWithAdmin($guestUser->getKey());

    expect($inbox1->id)->toBe($inbox2->id);
    expect(Inbox::count())->toBe(1);
});

it('ChatService returns null when no admin user exists', function (): void {
    $this->admin->delete();

    $guestUser = app(GuestIdentity::class)->user();
    $inbox     = ChatService::getOrCreateInboxWithAdmin($guestUser->getKey());

    expect($inbox)->toBeNull();
});

// ---------------------------------------------------------------------------
// Validation — guests cannot reach account-only pages
// ---------------------------------------------------------------------------

it('a guest is redirected to login when visiting account-only routes', function (string $path): void {
    get($path)->assertRedirect(route('filament.user.auth.login'));
})->with([
    '/welcome/carts',
    '/welcome/orders',
    '/welcome/wishlists',
    '/welcome/reviews',
]);
