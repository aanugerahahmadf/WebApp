<?php

use App\Filament\User\Pages\MessagesPage\MessagesPage;
use App\Jobs\SendBotReply\SendBotReply;
use App\Livewire\User\Messages\Inbox\Inbox;
use App\Livewire\User\Messages\Messages\Messages;
use App\Models\Inbox\Inbox as InboxModel;
use App\Models\Message\Message;
use App\Models\User\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

$admin = null;
$customer = null;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'super_admin']);
    Role::firstOrCreate(['name' => 'customer']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');

    $this->customer = User::factory()->create();
    $this->customer->assignRole('customer');

    Filament::setCurrentPanel(Filament::getPanel('user'));

    Queue::fake();
});

function makeInbox(User $customer, User $admin): InboxModel
{
    return InboxModel::create([
        'user_ids' => [$customer->id, $admin->id],
    ]);
}

it('loads the user conversations on the inbox', function () {
    $inbox = makeInbox($this->customer, $this->admin);
    $inbox->messages()->create([
        'message' => 'Halo, ini asisten dekorasi.',
        'user_id' => $this->admin->id,
        'read_by' => [$this->admin->id],
        'read_at' => [now()],
        'notified' => [$this->admin->id],
    ]);

    actingAs($this->customer);

    $component = Livewire::test(Inbox::class);

    $conversations = $component->get('conversations');
    expect($conversations)->toHaveCount(1);
    expect($conversations->first()->id)->toBe($inbox->id);
});

it('exposes the number of unread messages sent by the other participant', function () {
    $inbox = makeInbox($this->customer, $this->admin);
    $inbox->messages()->create([
        'message' => 'Belum dibaca oleh customer.',
        'user_id' => $this->admin->id,
        'read_by' => [$this->admin->id],
        'read_at' => [now()],
        'notified' => [$this->admin->id],
    ]);

    actingAs($this->customer);

    expect(Livewire::test(Inbox::class)->instance()->unreadCount())->toBe(1);

    $inbox->messages()->create([
        'message' => 'Dibaca oleh customer.',
        'user_id' => $this->admin->id,
        'read_by' => [$this->customer->id, $this->admin->id],
        'read_at' => [now(), now()],
        'notified' => [$this->admin->id, $this->customer->id],
    ]);

    expect(Livewire::test(Inbox::class)->instance()->unreadCount())->toBe(1);
});

it('filters conversations by inbox title and latest message content', function () {
    $decor = makeInbox($this->customer, $this->admin);
    $decor->update(['title' => 'Konsultasi Dekorasi']);
    $decor->messages()->create([
        'message' => 'Video referensi dekorasi sudah saya cek.',
        'user_id' => $this->admin->id,
        'read_by' => [$this->admin->id],
        'read_at' => [now()],
        'notified' => [$this->admin->id],
    ]);

    $payment = makeInbox($this->customer, $this->admin);
    $payment->update(['title' => 'Masalah Pembayaran']);
    $payment->messages()->create([
        'message' => 'Bukti transfer terkirim.',
        'user_id' => $this->admin->id,
        'read_by' => [$this->admin->id],
        'read_at' => [now()],
        'notified' => [$this->admin->id],
    ]);

    actingAs($this->customer);

    $component = Livewire::test(Inbox::class)
        ->set('search', 'dekorasi');

    $filtered = $component->get('filteredConversations');
    expect($filtered)->toHaveCount(1);
    expect($filtered->first()->id)->toBe($decor->id);

    $component->set('search', 'bukti transfer');
    expect($component->get('filteredConversations')->first()->id)->toBe($payment->id);

    $component->set('search', '');
    expect($component->get('filteredConversations'))->toHaveCount(2);
});

it('opens the most recent conversation from the New Chat button', function () {
    $inbox = makeInbox($this->customer, $this->admin);

    actingAs($this->customer);

    Livewire::test(Inbox::class)
        ->call('startNewChat')
        ->assertRedirect(MessagesPage::getUrl().'/'.$inbox->id);
});

it('creates an admin conversation when the user has none and starts a new chat', function () {
    actingAs($this->customer);

    $component = Livewire::test(Inbox::class)->call('startNewChat');

    $inbox = InboxModel::whereJsonContains('user_ids', $this->customer->id)->first();

    expect($inbox)->not->toBeNull();

    $component->assertRedirect(MessagesPage::getUrl().'/'.$inbox->id);
});

it('stores the cs category and sends the canned message from a quick-action card', function () {
    actingAs($this->customer);

    $component = Livewire::test(Inbox::class)
        ->call('startChatWithCategory', 'bug_report');

    $inbox = InboxModel::whereJsonContains('user_ids', $this->customer->id)->first();

    expect($inbox)->not->toBeNull();
    expect($inbox->meta['cs_category'])->toBe('bug_report');
    expect($inbox->messages()->count())->toBe(1);
    expect($inbox->messages()->first()->message)->toBe('Lapor Bug');

    $component->assertRedirect(MessagesPage::getUrl().'/'.$inbox->id);

    Queue::assertPushed(SendBotReply::class);
});

it('keeps the cs category when a category is chosen more than once', function () {
    $inbox = makeInbox($this->customer, $this->admin);
    $inbox->update(['meta' => ['cs_category' => 'order_help']]);

    actingAs($this->customer);

    Livewire::test(Inbox::class)
        ->call('startChatWithCategory', 'bug_report');

    $inbox->refresh();
    expect($inbox->meta['cs_category'])->toBe('order_help');
});

it('soft-deletes a conversation the user belongs to', function () {
    $inbox = makeInbox($this->customer, $this->admin);

    actingAs($this->customer);

    Livewire::test(Inbox::class)
        ->call('deleteConversation', $inbox->id)
        ->assertRedirect(MessagesPage::getUrl());

    $this->assertSoftDeleted('fm_inboxes', ['id' => $inbox->id]);
});

it('loads the conversation messages in the chat detail view', function () {
    $inbox = makeInbox($this->customer, $this->admin);
    $inbox->messages()->create([
        'message' => 'Halo customer, ada yang bisa dibantu?',
        'user_id' => $this->admin->id,
        'read_by' => [$this->admin->id],
        'read_at' => [now()],
        'notified' => [$this->admin->id],
    ]);
    $inbox->messages()->create([
        'message' => 'Saya mau tanya paket dekorasi.',
        'user_id' => $this->customer->id,
        'read_by' => [$this->customer->id],
        'read_at' => [now()],
        'notified' => [$this->customer->id],
    ]);

    actingAs($this->customer);

    $component = Livewire::test(Messages::class, ['selectedConversation' => $inbox]);

    $messages = $component->get('conversationMessages');
    expect($messages)->toHaveCount(2);
    expect($messages->contains(fn (Message $m) => $m->message === 'Saya mau tanya paket dekorasi.'))->toBeTrue();
});

it('sends a message and persists it to the conversation', function () {
    $inbox = makeInbox($this->customer, $this->admin);

    actingAs($this->customer);

    Livewire::test(Messages::class, ['selectedConversation' => $inbox])
        ->fillForm(['message' => 'Halo admin'])
        ->call('sendMessage');

    $this->assertDatabaseHas('fm_messages', [
        'message' => 'Halo admin',
    ]);

    Queue::assertPushed(SendBotReply::class);
});

it('stores the reply_to meta when replying to a message', function () {
    $inbox = makeInbox($this->customer, $this->admin);
    $original = $inbox->messages()->create([
        'message' => 'Bisa kirim contoh warna?',
        'user_id' => $this->admin->id,
        'read_by' => [$this->admin->id],
        'read_at' => [now()],
        'notified' => [$this->admin->id],
    ]);

    actingAs($this->customer);

    Livewire::test(Messages::class, ['selectedConversation' => $inbox])
        ->call('setReplyTo', $original->id)
        ->fillForm(['message' => 'Tentu, saya kirim.'])
        ->call('sendMessage');

    $reply = Message::where('message', 'Tentu, saya kirim.')->first();

    expect($reply)->not->toBeNull();
    expect($reply->meta['reply_to']['id'])->toBe($original->id);
});

it('toggles an emoji reaction for the current user', function () {
    $inbox = makeInbox($this->customer, $this->admin);
    $msg = $inbox->messages()->create([
        'message' => 'Terima kasih!',
        'user_id' => $this->customer->id,
        'read_by' => [$this->customer->id],
        'read_at' => [now()],
        'notified' => [$this->customer->id],
    ]);

    actingAs($this->customer);

    $component = Livewire::test(Messages::class, ['selectedConversation' => $inbox]);

    $component->call('toggleReaction', $msg->id, '👍');
    $msg->refresh();
    expect($msg->meta['reactions'])->toHaveCount(1);
    expect($msg->meta['reactions'][0]['user_id'])->toBe((string) $this->customer->id);
    expect($msg->meta['reactions'][0]['emoji'])->toBe('👍');

    $component->call('toggleReaction', $msg->id, '👍');
    $msg->refresh();
    expect($msg->meta['reactions'] ?? [])->toBe([]);
});

it('toggles a star for the current user', function () {
    $inbox = makeInbox($this->customer, $this->admin);
    $msg = $inbox->messages()->create([
        'message' => 'Penting',
        'user_id' => $this->admin->id,
        'read_by' => [$this->admin->id],
        'read_at' => [now()],
        'notified' => [$this->admin->id],
    ]);

    actingAs($this->customer);

    $component = Livewire::test(Messages::class, ['selectedConversation' => $inbox]);

    $component->call('toggleStar', $msg->id);
    $msg->refresh();
    expect($msg->meta['starred_by'])->toBe([(string) $this->customer->id]);

    $component->call('toggleStar', $msg->id);
    $msg->refresh();
    expect($msg->meta['starred_by'] ?? [])->toBe([]);
});

it('hides a message only for the current user with delete me', function () {
    $inbox = makeInbox($this->customer, $this->admin);
    $msg = $inbox->messages()->create([
        'message' => 'Rahasia',
        'user_id' => $this->admin->id,
        'read_by' => [$this->admin->id],
        'read_at' => [now()],
        'notified' => [$this->admin->id],
    ]);

    actingAs($this->customer);

    Livewire::test(Messages::class, ['selectedConversation' => $inbox])
        ->call('deleteMessage', $msg->id, 'me');

    $msg->refresh();
    expect($msg->meta['deleted_by'])->toBe([$this->customer->id]);
    expect($msg->trashed())->toBeFalse();
});

it('soft-deletes a message for everyone when the sender deletes it', function () {
    $inbox = makeInbox($this->customer, $this->admin);
    $msg = $inbox->messages()->create([
        'message' => 'Pesan salah kirim',
        'user_id' => $this->customer->id,
        'read_by' => [$this->customer->id],
        'read_at' => [now()],
        'notified' => [$this->customer->id],
    ]);

    actingAs($this->customer);

    Livewire::test(Messages::class, ['selectedConversation' => $inbox])
        ->call('deleteMessage', $msg->id, 'everyone');

    $this->assertSoftDeleted('fm_messages', ['id' => $msg->id]);
});

it('does not let a non-admin user delete another participant message for everyone', function () {
    $inbox = makeInbox($this->customer, $this->admin);
    $msg = $inbox->messages()->create([
        'message' => 'Pesan dari admin',
        'user_id' => $this->admin->id,
        'read_by' => [$this->admin->id],
        'read_at' => [now()],
        'notified' => [$this->admin->id],
    ]);

    actingAs($this->customer);

    Livewire::test(Messages::class, ['selectedConversation' => $inbox])
        ->call('deleteMessage', $msg->id, 'everyone');

    // The other participant's message must NOT be deleted by a regular user.
    $this->assertDatabaseHas('fm_messages', ['id' => $msg->id, 'deleted_at' => null]);

    $msg->refresh();
    expect($msg->meta['deleted_by'] ?? [])->toBe([]);
});

it('mounts the message info action', function () {
    $inbox = makeInbox($this->customer, $this->admin);
    $msg = $inbox->messages()->create([
        'message' => 'Info pesan ini.',
        'user_id' => $this->admin->id,
        'read_by' => [$this->admin->id],
        'read_at' => [now()],
        'notified' => [$this->admin->id],
    ]);

    actingAs($this->customer);

    Livewire::test(Messages::class, ['selectedConversation' => $inbox])
        ->assertActionExists('messageInfo')
        ->callAction('messageInfo', arguments: ['messageId' => $msg->id])
        ->assertHasNoActionErrors();
});

it('mounts the translate action even when the message has no text', function () {
    $inbox = makeInbox($this->customer, $this->admin);
    $msg = $inbox->messages()->create([
        'message' => null,
        'user_id' => $this->admin->id,
        'read_by' => [$this->admin->id],
        'read_at' => [now()],
        'notified' => [$this->admin->id],
    ]);

    actingAs($this->customer);

    Livewire::test(Messages::class, ['selectedConversation' => $inbox])
        ->assertActionExists('translateMessage')
        ->callAction('translateMessage', arguments: ['messageId' => $msg->id])
        ->assertHasNoActionErrors();
});

it('stores the CS rating on the inbox via the rate experience action', function () {
    $inbox = makeInbox($this->customer, $this->admin);

    actingAs($this->customer);

    Livewire::test(Messages::class, ['selectedConversation' => $inbox])
        ->callAction('rateExperience', ['rating' => 5, 'comment' => 'Sangat membantu!']);

    $inbox->refresh();
    expect($inbox->meta['cs_rating']['rating'])->toBe(5);
    expect($inbox->meta['cs_rating']['comment'])->toBe('Sangat membantu!');
    expect($inbox->meta['cs_rating']['user_id'])->toBe($this->customer->id);
});

it('dispatches the message highlight event', function () {
    $inbox = makeInbox($this->customer, $this->admin);
    $msg = $inbox->messages()->create([
        'message' => 'Pesan highlight.',
        'user_id' => $this->admin->id,
        'read_by' => [$this->admin->id],
        'read_at' => [now()],
        'notified' => [$this->admin->id],
    ]);

    actingAs($this->customer);

    Livewire::test(Messages::class, ['selectedConversation' => $inbox])
        ->call('highlightMessage', $msg->id)
        ->assertDispatched('chat-highlight-message');
});