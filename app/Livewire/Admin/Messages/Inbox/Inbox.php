<?php

namespace App\Livewire\Admin\Messages\Inbox;

use App\Filament\Admin\Pages\MessagesPage\MessagesPage as AdminMessagesPage;
use App\Livewire\Traits\CanMarkAsRead\CanMarkAsRead;
use App\Livewire\Traits\CanValidateFiles\CanValidateFiles;
use App\Livewire\Traits\HasPollInterval\HasPollInterval;
use App\Models\Inbox\Inbox as InboxModel;
use App\Models\User\User;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * @mixin Component
 */
class Inbox extends Component implements HasActions, HasForms
{
    use CanMarkAsRead, CanValidateFiles, HasPollInterval, InteractsWithActions, InteractsWithForms;

    public $conversations;

    public $selectedConversation;

    public string $panelId = 'admin';

    public function mount(): void
    {
        $this->setPollInterval();
        $this->loadConversations();
    }

    public function unreadCount(): int
    {
        $userId = Auth::id();

        /** @var Builder $query */
        $query = InboxModel::whereJsonContains('user_ids', $userId, 'and', false);

        return $query->whereHas('messages', function (Builder $q) use ($userId): void {
            $q->whereJsonDoesntContain('read_by', $userId, 'and', false);
        })->count();
    }

    #[On('refresh-inbox')]
    public function loadConversations(): void
    {
        $query = Auth::user()->allConversations();

        $this->conversations = $query->get(['*']);
        $this->markAsRead();
    }

    public function createConversationAction(): Action
    {
        return Action::make('createConversation')
            ->icon('heroicon-o-plus')
            ->label(__('Create'))
            ->form([
                Forms\Components\Select::make('user_ids')
                    ->label(__('Select User'))
                    ->options(function () {
                        $query = User::query();

                        // Admin can talk to anyone except themselves
                        return $query->where('id', '!=', Auth::id())->pluck('full_name', 'id')->toArray();
                    })
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->required()
                    ->live(),
                Forms\Components\TextInput::make('title')
                    ->label(__('Group Name'))
                    ->visible(function (Forms\Get $get) {
                        return collect($get('user_ids') ?? [])->count() > 1;
                    }),
                Forms\Components\Textarea::make('message')
                    ->placeholder(__('Write a message...'))
                    ->required()
                    ->autosize(),
            ])
            ->modalHeading(__('Create New Message'))
            ->modalSubmitActionLabel(__('Send'))
            ->modalWidth(MaxWidth::Large)
            ->action(function (array $data) {
                $userIds = collect($data['user_ids'])->push(Auth::id())->map(fn ($userId) => (int) $userId)->unique()->sort()->values();
                $totalUserIds = $userIds->count();

                /** @var InboxModel|null $inbox */
                $inbox = InboxModel::query()
                    ->whereJsonContains('user_ids', $userIds->toArray(), 'and', false)
                    ->whereRaw('JSON_LENGTH(user_ids) = ?', [$totalUserIds])
                    ->when($data['title'] ?? null, function ($query, $title) {
                        return $query->where('title', $title);
                    })
                    ->when(! ($data['title'] ?? null), function ($query) {
                        return $query->whereNull('title');
                    })
                    ->first(['*']);

                $inboxId = null;
                if (! $inbox) {
                    $inbox = InboxModel::create([
                        'title' => $data['title'] ?? null,
                        'user_ids' => $userIds,
                    ]);
                    $inboxId = $inbox->getKey();
                } else {
                    /** @var InboxModel $inbox */
                    $inbox->updated_at = now();
                    $inbox->save();
                    $inboxId = $inbox->getKey();
                }
                $inbox->messages()->create([
                    'message' => $data['message'],
                    'user_id' => Auth::id(),
                    'read_by' => [Auth::id()],
                    'read_at' => [now()],
                    'notified' => [Auth::id()],
                ]);

                $redirectUrl = AdminMessagesPage::getUrl().'/'.$inboxId;

                return redirect()->to($redirectUrl);
            })->button();
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

            $redirectUrl = AdminMessagesPage::getUrl();

            return $this->redirect($redirectUrl);
        }
    }

    public function render(): Application|Factory|View|\Illuminate\View\View
    {
        return view('Admin.livewire.messages.inbox.inbox');
    }
}
