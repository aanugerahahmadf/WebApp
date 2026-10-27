<?php

/**
 * Tests for guest chat via GuestIdentity on the Welcome panel.
 *
 * The old GuestChatTest hit /api/messages/guest/* JSON endpoints.
 * Those endpoints no longer exist. Guest chat now works through:
 *   1. The "Chat Admin" infolist action on catalog detail pages
 *      (uses GuestIdentity to get/create a guest user row, then creates
 *       an Inbox and redirects to /messages/{id})
 *   2. The MessagesPage which verifies the guest is in inbox.user_ids
 *
 * These tests pin down the GuestIdentity + ChatService integration on
 * the Welcome panel path.
 */

use App\Filament\Welcome\Resources\CatalogResource\Pages\ViewCatalog\ViewCatalog;
use App\Http\Middleware\AuthenticateWelcome\AuthenticateWelcome;
use App\Models\Inbox\Inbox;
use App\Models\Admin\Admin;
use App\Models\Catalog\Catalog;
use App\Models\User\User;
use App\Services\ChatService\ChatService;
use App\Services\GuestIdentity\GuestIdentity;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Queue::fake();

    // Role `super_admin` hidup di guard `admin`, bukan `web` -- lihat
    // `Admin::factory()->superAdmin()` yang mencari role di guard model.
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'admin']);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

    // Admin untuk chat harus punya identitas belanja tertaut di `admins.user_id`.
    // `ChatStaff::adminUser()` mencari `Admin::whereNotNull('user_id')`, jadi
    // `User::factory()` + `assignRole('super_admin')` tidak cukup -- role itu
    // sudah pindah ke tabel `admins` bersama tabelnya. Tanpa ini, chat berhenti
    // dengan notifikasi "Fitur chat belum tersedia" dan tidak ada redirect.
    $this->admin = Admin::factory()->superAdmin()->create([
        'active_status' => true,
        'user_id'       => User::factory()->create()->id,
    ]);

    // `name_translations`, bukan `name` -- lihat catatan di `CatalogFactory`.
    $this->catalog = Catalog::factory()->create([
        'stock'             => 5,
        'name_translations' => ['id' => 'Bunga Tamu', 'en' => 'Guest Flower'],
    ]);

    Filament::setCurrentPanel(Filament::getPanel('welcome'));
});

// ---------------------------------------------------------------------------
// GuestIdentity â€” user creation
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

it('a guest can start a conversation by clicking Chat Admin on a catalog page', function (): void {
    // No actingAs â€” purely anonymous
    Livewire::test(ViewCatalog::class, ['record' => $this->catalog->getKey()])
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

/*
 * Test "a guest can start a conversation by clicking Chat Admin on a package
 * page" DIHAPUS. Ia memanggil `ViewPackage::class`, yang sudah tidak ada
 * bersama `PackageResource`-nya.
 *
 * Yang hilang bukan alur untuk tamu: test di atasnya menguji klik pertama pada
 * halaman katalog dan test berikutnya menguji klik kedua yang memakai inbox
 * yang sama. Dua-duanya lewat `GuestIdentity`, jadi penutup untuk record
 * `Package` tidak menambah cabang kode yang baru.
 */

it('a second Chat Admin click from the same guest reuses the same inbox', function (): void {
    // First click
    Livewire::test(ViewCatalog::class, ['record' => $this->catalog->getKey()])
        ->callInfolistAction('.chat_adminAction', 'chat_admin');

    $countAfterFirst = Inbox::count();

    // Second click (same guest session â€” GuestIdentity uses the same user row)
    Livewire::test(ViewCatalog::class, ['record' => $this->catalog->getKey()])
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

    get("/messages/{$inbox->id}")->assertOk();
});

it('a guest cannot access another guest\'s inbox', function (): void {
    $otherGuest = User::factory()->create(['email' => 'guest_other@guest.local']);
    $inbox      = Inbox::create(['user_ids' => [$otherGuest->id, $this->admin->id]]);

    $currentGuest = app(GuestIdentity::class)->user();
    actingAs($currentGuest, 'web');

    get("/messages/{$inbox->id}")->assertForbidden();
});

it('a guest cannot access a nonexistent inbox', function (): void {
    $guestUser = app(GuestIdentity::class)->user();
    actingAs($guestUser, 'web');

    get('/messages/999999')->assertNotFound();
});

// ---------------------------------------------------------------------------
// ChatService â€” getOrCreateInboxWithAdmin
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

it('guest mobile chat receives the shared internet call room', function (): void {
    $started = $this->postJson('/api/messages/guest/start', [
        'guest_id' => 'guest-call-test',
        'name' => 'Call Guest',
    ])->assertCreated();

    $inboxId = $started->json('data.id');

    $this->getJson('/api/messages/guest/'.$inboxId.'?guest_id=guest-call-test')
        ->assertOk()
        ->assertJsonPath('other_user.call_url', fn ($url) => is_string($url) && str_contains($url, 'meet.jit.si/WeddingFlowerDecorations-'.$inboxId.'-'));
});

// ---------------------------------------------------------------------------
// Validation â€” guests cannot reach account-only pages
// ---------------------------------------------------------------------------

it('a guest is redirected to the auth landing page when visiting account-only routes', function (string $path): void {
    // /auth (Sign In + Continue With Google), bukan form /signin --
    // middleware meneruskan tamu ke AuthenticateWelcome::LOGIN_ROUTE.
    get($path)->assertRedirect(route(AuthenticateWelcome::LOGIN_ROUTE));
})->with([
    '/carts',
    '/orders',
    '/wishlists',
    '/reviews',
]);

