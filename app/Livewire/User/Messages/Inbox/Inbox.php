<?php

namespace App\Livewire\User\Messages\Inbox;

use App\Filament\User\Pages\MessagesPage\MessagesPage as UserMessagesPage;
use App\Jobs\SendBotReply\SendBotReply;
use App\Livewire\Traits\CanMarkAsRead\CanMarkAsRead;
use App\Livewire\Traits\CanValidateFiles\CanValidateFiles;
use App\Livewire\Traits\HasPollInterval\HasPollInterval;
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
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * @mixin Component
 */
class Inbox extends Component implements HasActions, HasForms
{
    use CanMarkAsRead, CanValidateFiles, HasPollInterval, InteractsWithActions, InteractsWithForms;

    public $conversations;

    public string $search = '';

    public function mount(): void
    {
        $this->setPollInterval();
        $this->loadConversations();
    }

    public function unreadCount(): int
    {
        $userId = Auth::id();

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
        // Match the mobile `getConversations` endpoint: every inbox the user
        // belongs to, newest activity first.
        $this->conversations = Auth::user()->allConversations()->with('messages')->get(['*']);
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
            UserMessagesPage::getUrl(['id' => $inbox->getKey()]),
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
        if (empty($meta['cs_category'])) {
            $meta['cs_category'] = $category;
            $inbox->meta = $meta;
            $inbox->save();
        }

        if ($message = $this->categoryMessage($category)) {
            $newMessage = $inbox->messages()->create([
                'message' => $message,
                'user_id' => Auth::id(),
                'read_by' => [Auth::id()],
                'read_at' => [now()],
                'notified' => [Auth::id()],
            ]);

            if (! Auth::user()->hasRole('super_admin')) {
                SendBotReply::dispatch($newMessage->id)->delay(now()->addSeconds(5));
            }
        }

        $this->redirect(
            UserMessagesPage::getUrl(['id' => $inbox->getKey()]),
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
        $userId = Auth::id();

        $admin = User::query()->whereHas('roles', function ($q) {
            $q->where('name', 'super_admin');
        })->first();

        if (! $admin) {
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

        if ($inbox && in_array(Auth::id(), $inbox->user_ids)) {
            $inbox->delete();

            Notification::make()
                ->title(__('Conversation deleted'))
                ->success()
                ->send();

            return $this->redirect(UserMessagesPage::getUrl());
        }
    }

    public function render(): Application|Factory|View|\Illuminate\View\View
    {
        return view('User.livewire.messages.inbox.inbox');
    }
}
