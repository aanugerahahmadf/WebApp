<?php

namespace App\Livewire\Welcome\Messages\Inbox;

use App\Filament\Welcome\Pages\MessagesPage\MessagesPage as WelcomeMessagesPage;
use App\Jobs\SendBotReply\SendBotReply;
use App\Livewire\Welcome\Traits\CanMarkAsRead\CanMarkAsRead;
use App\Livewire\Welcome\Traits\CanValidateFiles\CanValidateFiles;
use App\Livewire\Welcome\Traits\HasPollInterval\HasPollInterval;
use App\Models\Inbox\Inbox as InboxModel;
use App\Models\User\User;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Application;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Conversation list for the welcome storefront.
 *
 * Serves guests as readily as members: the panel is public, customer service
 * is the one thing a visitor may use before deciding to register, and the
 * mobile app already lets a logged-out device talk to an admin through the same
 * `@guest.local` account shape. So identity comes from chatUserId() rather than
 * Auth::id(), which is null for a guest and would quietly produce an empty
 * inbox. See InteractsWithGuestIdentity.
 *
 * @mixin Component
 */
class Inbox extends Component implements HasActions, HasForms
{
    use CanMarkAsRead, CanValidateFiles, HasPollInterval, InteractsWithActions, InteractsWithForms;

    public $conversations;

    public string $search = '';

    public string $guestName = '';

    public function mount(): void
    {
        $this->setPollInterval();
        $this->loadConversations();
    }

    public function needsName(): bool
    {
        return $this->isChatGuest() && app(\App\Services\GuestIdentity\GuestIdentity::class)->needsName();
    }

    public function setGuestName(): void
    {
        $this->validate(['guestName' => 'required|string|max:100']);
        $this->chatUser()->forceFill(['full_name' => $this->guestName])->save();
        $this->guestName = '';
    }

    public function unreadCount(): int
    {
        $userId = $this->chatUserId();

        if ($userId === null) {
            return 0;
        }

        /** @var \Illuminate\Database\Eloquent\Builder $query */
        $query = InboxModel::whereJsonContains('user_ids', $userId, 'and', false);

        return $query->whereHas('messages', function ($q) use ($userId): void {
            $q->where('user_id', '!=', $userId)
                ->whereJsonDoesntContain('read_by', $userId, 'and', false);
        })->count();
    }

    #[On('refresh-inbox')]
    public function loadConversations(): void
    {
        // Match the mobile `getConversations` endpoint: every inbox the viewer
        // belongs to, newest activity first. Scoped to the resolved identity so
        // a guest sees their own thread and nobody else's.
        $this->conversations = $this->chatUser()
            ?->allConversations()
            ->with('messages')
            ->get(['*'])
            ?? collect();
    }

    #[Computed]
    public function filteredConversations()
    {
        $query = trim(mb_strtolower($this->search));

        if ($query === '') {
            return $this->conversations;
        }

        return $this->conversations->filter(function (InboxModel $conversation) use ($query) {
            $latestMessage = $conversation->messages->sortByDesc('id')->first();

            $haystacks = [
                (string) $conversation->inbox_title,
                (string) ($latestMessage->message ?? ''),
            ];

            foreach ($haystacks as $haystack) {
                if (str_contains(mb_strtolower($haystack), $query)) {
                    return true;
                }
            }

            return false;
        })->values();
    }

    /**
     * CS quick-action cards, mirroring `chat_list_page.dart`.
     *
     * @return array<int, array<string, string>>
     */
    public function csActions(): array
    {
        return [
            [
                'key' => 'bug_report',
                'icon' => 'heroicon-o-bug-ant',
                'color' => 'text-orange-500 bg-orange-500/10',
                'title' => __('Lapor Bug'),
                'subtitle' => __('Laporkan masalah atau error'),
            ],
            [
                'key' => 'account_issue',
                'icon' => 'heroicon-o-user',
                'color' => 'text-blue-500 bg-blue-500/10',
                'title' => __('Masalah Akun'),
                'subtitle' => __('Masalah dengan akun Anda'),
            ],
            [
                'key' => 'order_help',
                'icon' => 'heroicon-o-clipboard-document-list',
                'color' => 'text-green-500 bg-green-500/10',
                'title' => __('Bantuan Pesanan'),
                'subtitle' => __('Bantuan terkait pesanan'),
            ],
            [
                'key' => 'payment_issue',
                'icon' => 'heroicon-o-credit-card',
                'color' => 'text-red-500 bg-red-500/10',
                'title' => __('Masalah Pembayaran'),
                'subtitle' => __('Masalah dengan pembayaran'),
            ],
            [
                'key' => 'decor_consultation',
                'icon' => 'heroicon-o-sparkles',
                'color' => 'text-pink-500 bg-pink-500/10',
                'title' => __('Konsultasi Dekorasi'),
                'subtitle' => __('Konsultasi tentang dekorasi'),
            ],
            [
                'key' => 'general_question',
                'icon' => 'heroicon-o-question-mark-circle',
                'color' => 'text-teal-500 bg-teal-500/10',
                'title' => __('Pertanyaan Umum'),
                'subtitle' => __('Tanya apa saja'),
            ],
        ];
    }

    /**
     * Match the mobile New Chat button: open the most recent conversation, or
     * create one with the admin when the user has none yet.
     */
    public function startNewChat(): void
    {
        $inbox = $this->conversations->first() ?? $this->findOrCreateAdminConversation();

        if (! $inbox) {
            Notification::make()
                ->title(__('No admin available'))
                ->danger()
                ->send();

            return;
        }

        $this->redirect(
            WelcomeMessagesPage::getUrl(['id' => $inbox->getKey()]),
            navigate: true,
        );
    }

    /**
     * Start a chat from a CS quick-action card. Mirrors the mobile behaviour:
     * open the most recent conversation (or create one with admin), store the
     * category on the inbox, then send the canned category message so the bot
     * can reply with the matching flow.
     */
    public function startChatWithCategory(string $category): void
    {
        $inbox = $this->conversations->first() ?? $this->findOrCreateAdminConversation();

        if (! $inbox) {
            Notification::make()
                ->title(__('No admin available'))
                ->danger()
                ->send();

            return;
        }

        $meta = $inbox->meta ?? [];
        $meta['cs_category'] = $category;
        $inbox->meta = $meta;
        $inbox->save();

        if ($message = $this->categoryMessage($category)) {
            $userId = $this->chatUserId();

            $newMessage = $inbox->messages()->create([
                'message' => $message,
                'user_id' => $userId,
                'read_by' => [$userId],
                'read_at' => [now()],
                'notified' => [$userId],
            ]);

            if (! $this->chatUser()?->hasRole('super_admin')) {
                SendBotReply::dispatch($newMessage->id)->delay(now()->addSeconds(5));
            }
        }

        $this->redirect(
            WelcomeMessagesPage::getUrl(['id' => $inbox->getKey()]),
            navigate: true,
        );
    }

    protected function categoryMessage(string $category): ?string
    {
        return match ($category) {
            'bug_report' => __('Lapor Bug'),
            'account_issue' => __('Masalah Akun'),
            'order_help' => __('Bantuan Pesanan'),
            'payment_issue' => __('Masalah Pembayaran'),
            'decor_consultation' => __('Konsultasi Dekorasi'),
            'general_question' => __('Pertanyaan Umum'),
            default => null,
        };
    }

    protected function findOrCreateAdminConversation(): ?InboxModel
    {
        $userId = $this->chatUserId();

        $admin = User::query()->whereHas('roles', function ($q) {
            $q->where('name', 'super_admin');
        })->first();

        if (! $userId || ! $admin) {
            return null;
        }

        /** @var InboxModel|null $inbox */
        $inbox = InboxModel::query()
            ->whereJsonContains('user_ids', $userId, 'and', false)
            ->whereJsonContains('user_ids', $admin->id, 'and', false)
            ->first();

        if (! $inbox) {
            $inbox = InboxModel::create([
                'user_ids' => [$userId, $admin->id],
            ]);
        }

        return $inbox;
    }

    public function deleteConversation(int $id)
    {
        /** @var InboxModel|null $inbox */
        $inbox = InboxModel::find($id, ['*']);

        if ($inbox && $this->isChatParticipant($inbox)) {
            $inbox->delete();

            Notification::make()
                ->title(__('Conversation deleted'))
                ->success()
                ->send();

            return $this->redirect(WelcomeMessagesPage::getUrl());
        }
    }

    public function render(): Application|Factory|View|\Illuminate\View\View
    {
        return view('Welcome.livewire.messages.inbox.inbox');
    }
}
