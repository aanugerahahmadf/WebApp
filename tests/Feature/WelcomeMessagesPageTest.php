<?php

/**
 * Tests for the Welcome panel MessagesPage.
 *
 * Covers:
 * - Page renders for a signed-in user
 * - Page renders for a guest (GuestIdentity)
 * - Pre-selected conversation loads correctly
 * - Guest cannot access another user's conversation (403)
 * - hasRole() null-safety bug does not cause 500
 * - Navigation badge shows unread count
 * - Slug format includes optional {id?}
 */

use App\Filament\Welcome\Pages\MessagesPage\MessagesPage;
use App\Livewire\Welcome\Messages\Messages\Messages;
use App\Models\Inbox\Inbox;
use App\Models\Message\Message;
use App\Models\User\User;
use App\Services\GuestIdentity\GuestIdentity;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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

    $this->customer = User::factory()->create();
    $this->customer->assignRole('customer');

    Filament::setCurrentPanel(Filament::getPanel('welcome'));
});

// ---------------------------------------------------------------------------
// Slug & URL
// ---------------------------------------------------------------------------

it('messages page slug contains the optional id segment', function (): void {
    expect(MessagesPage::getSlug())->toContain('{id?}');
});

it('messages page URL resolves to the welcome panel path', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('welcome'));
    $url = MessagesPage::getUrl(panel: 'welcome');
    expect($url)->toContain('/welcome/messages');
});

// ---------------------------------------------------------------------------
// Rendering — authenticated
// ---------------------------------------------------------------------------

it('renders the messages page for a signed-in customer', function (): void {
    actingAs($this->customer, 'web');
    get('/welcome/messages')->assertOk();
});

it('renders the messages page with a pre-selected conversation', function (): void {
    actingAs($this->customer, 'web');

    $inbox = Inbox::create(['user_ids' => [$this->customer->id, $this->admin->id]]);
    Message::create([
        'inbox_id' => $inbox->id,
        'user_id'  => $this->admin->id,
        'message'  => 'Halo, ada yang bisa kami bantu?',
        'read_by'  => [$this->admin->id],
    ]);

    get("/welcome/messages/{$inbox->id}")->assertOk();
});

// ---------------------------------------------------------------------------
// hasRole() null-safety — the 500 bug regression test
// ---------------------------------------------------------------------------

it('does not 500 when a guest opens a messages conversation (null-safe hasRole)', function (): void {
    // Create a guest user and an inbox for them
    $guestUser = User::factory()->create(['email' => 'guest_regression@guest.local']);
    $inbox     = Inbox::create(['user_ids' => [$guestUser->id, $this->admin->id]]);
    Message::create([
        'inbox_id' => $inbox->id,
        'user_id'  => $this->admin->id,
        'message'  => 'Welcome to our store!',
        'read_by'  => [$this->admin->id],
    ]);

    // Act as the guest user (authenticated but no roles assigned)
    actingAs($guestUser, 'web');

    get("/welcome/messages/{$inbox->id}")
        ->assertOk(); // must NOT be 500
});

it('does not 500 when the messages livewire component renders for a guest-identity user', function (): void {
    $guestUser = User::factory()->create(['email' => 'guest_livewire@guest.local']);
    $inbox     = Inbox::create(['user_ids' => [$guestUser->id, $this->admin->id]]);

    Message::create([
        'inbox_id' => $inbox->id,
        'user_id'  => $this->admin->id,
        'message'  => 'Hi there!',
        'read_by'  => [$this->admin->id],
    ]);

    actingAs($guestUser, 'web');

    Livewire::test(Messages::class, [
        'selectedConversation' => $inbox,
    ])->assertSuccessful();
});

// ---------------------------------------------------------------------------
// Access control
// ---------------------------------------------------------------------------

it('a customer cannot open another customers conversation (403)', function (): void {
    actingAs($this->customer, 'web');

    $otherUser = User::factory()->create();
    $inbox     = Inbox::create(['user_ids' => [$otherUser->id, $this->admin->id]]);

    get("/welcome/messages/{$inbox->id}")->assertForbidden();
});

it('the admin can open any conversation', function (): void {
    actingAs($this->admin, 'web');

    $inbox = Inbox::create(['user_ids' => [$this->customer->id, $this->admin->id]]);

    get("/welcome/messages/{$inbox->id}")->assertOk();
});

// ---------------------------------------------------------------------------
// Navigation badge — unread count
// ---------------------------------------------------------------------------

it('navigation badge returns null when there are no unread messages', function (): void {
    actingAs($this->customer, 'web');
    Cache::flush();

    Filament::setCurrentPanel(Filament::getPanel('welcome'));

    // No inbox => no unread
    expect(MessagesPage::getNavigationBadge())->toBeNull();
});

it('navigation badge returns the unread inbox count', function (): void {
    $userId = $this->customer->id;

    $inbox = Inbox::create(['user_ids' => [$userId, $this->admin->id]]);
    Message::create([
        'inbox_id' => $inbox->id,
        'user_id'  => $this->admin->id,
        'message'  => 'Unread message',
        'read_by'  => [$this->admin->id], // customer has NOT read it
    ]);

    actingAs($this->customer, 'web');
    Cache::flush();
    Filament::setCurrentPanel(Filament::getPanel('welcome'));

    // The badge should be '1'
    $badge = MessagesPage::getNavigationBadge();
    expect($badge)->toBe('1');
});

// ---------------------------------------------------------------------------
// Livewire — conversation interactions
// ---------------------------------------------------------------------------

it('can send a message in a conversation', function (): void {
    actingAs($this->customer, 'web');

    $inbox = Inbox::create(['user_ids' => [$this->customer->id, $this->admin->id]]);

    Livewire::test(Messages::class, ['selectedConversation' => $inbox])
        ->set('data.message', 'Halo admin!')
        ->call('sendMessage');

    $this->assertDatabaseHas('fm_messages', [
        'inbox_id' => $inbox->id,
        'user_id'  => $this->customer->id,
        'message'  => 'Halo admin!',
    ]);
});

it('can toggle a reaction on a message', function (): void {
    actingAs($this->customer, 'web');

    $inbox   = Inbox::create(['user_ids' => [$this->customer->id, $this->admin->id]]);
    $message = Message::create([
        'inbox_id' => $inbox->id,
        'user_id'  => $this->admin->id,
        'message'  => 'Hai!',
        'read_by'  => [$this->admin->id],
    ]);

    Livewire::test(Messages::class, ['selectedConversation' => $inbox])
        ->call('toggleReaction', $message->id, '👍');

    $message->refresh();
    $reactions = $message->meta['reactions'] ?? [];
    $userIds   = array_column($reactions, 'user_id');
    expect($userIds)->toContain((string) $this->customer->id);
});

it('can star a message', function (): void {
    actingAs($this->customer, 'web');

    $inbox   = Inbox::create(['user_ids' => [$this->customer->id, $this->admin->id]]);
    $message = Message::create([
        'inbox_id' => $inbox->id,
        'user_id'  => $this->admin->id,
        'message'  => 'Star me!',
        'read_by'  => [$this->admin->id],
    ]);

    Livewire::test(Messages::class, ['selectedConversation' => $inbox])
        ->call('toggleStar', $message->id);

    $message->refresh();
    expect($message->meta['starred_by'] ?? [])
        ->toContain((string) $this->customer->id);
});

it('can soft-delete a message for myself', function (): void {
    actingAs($this->customer, 'web');

    $inbox   = Inbox::create(['user_ids' => [$this->customer->id, $this->admin->id]]);
    $message = Message::create([
        'inbox_id' => $inbox->id,
        'user_id'  => $this->admin->id,
        'message'  => 'Delete me!',
        'read_by'  => [$this->admin->id],
    ]);

    Livewire::test(Messages::class, ['selectedConversation' => $inbox])
        ->call('deleteMessage', $message->id, 'me');

    $message->refresh();
    expect($message->meta['deleted_by'] ?? [])
        ->toContain($this->customer->id);

    // Row still exists in DB
    $this->assertDatabaseHas('fm_messages', ['id' => $message->id]);
});

it('hard-deletes a message when user deletes for everyone (own message)', function (): void {
    actingAs($this->customer, 'web');

    $inbox   = Inbox::create(['user_ids' => [$this->customer->id, $this->admin->id]]);
    $message = Message::create([
        'inbox_id' => $inbox->id,
        'user_id'  => $this->customer->id, // own message
        'message'  => 'Delete for everyone!',
        'read_by'  => [$this->customer->id],
    ]);

    Livewire::test(Messages::class, ['selectedConversation' => $inbox])
        ->call('deleteMessage', $message->id, 'everyone');

    $this->assertDatabaseMissing('fm_messages', ['id' => $message->id]);
});

it('can set a reply-to reference', function (): void {
    actingAs($this->customer, 'web');

    $inbox   = Inbox::create(['user_ids' => [$this->customer->id, $this->admin->id]]);
    $message = Message::create([
        'inbox_id' => $inbox->id,
        'user_id'  => $this->admin->id,
        'message'  => 'Reply to me!',
        'read_by'  => [$this->admin->id],
    ]);

    Livewire::test(Messages::class, ['selectedConversation' => $inbox])
        ->call('setReplyTo', $message->id)
        ->assertSet('replyTo.id', $message->id)
        ->assertSet('replyTo.message', 'Reply to me!');
});

it('can clear the reply-to reference', function (): void {
    actingAs($this->customer, 'web');

    $inbox   = Inbox::create(['user_ids' => [$this->customer->id, $this->admin->id]]);
    $message = Message::create([
        'inbox_id' => $inbox->id,
        'user_id'  => $this->admin->id,
        'message'  => 'Clear me!',
        'read_by'  => [$this->admin->id],
    ]);

    Livewire::test(Messages::class, ['selectedConversation' => $inbox])
        ->call('setReplyTo', $message->id)
        ->call('clearReplyTo')
        ->assertSet('replyTo', null);
});
