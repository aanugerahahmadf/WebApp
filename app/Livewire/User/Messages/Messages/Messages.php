<?php

namespace App\Livewire\User\Messages\Messages;

use App\Enums\DiscountType\DiscountType;
use App\Enums\Messages\MediaCollectionType\MediaCollectionType;
use App\Enums\OrderPaymentStatus\OrderPaymentStatus;
use App\Enums\OrderStatus\OrderStatus;
use App\Filament\User\Pages\MessagesPage\MessagesPage;
use App\Filament\User\Resources\PackageResource\PackageResource;
use App\Filament\User\Resources\ProductResource\ProductResource;
use App\Jobs\SendBotReply\SendBotReply;
use App\Livewire\Traits\CanMarkAsRead\CanMarkAsRead;
use App\Livewire\Traits\CanValidateFiles\CanValidateFiles;
use App\Livewire\Traits\HasPollInterval\HasPollInterval;
use App\Models\Inbox\Inbox;
use App\Models\Message\Message;
use App\Models\Order\Order;
use App\Models\Package\Package;
use App\Models\Product\Product;
use App\Models\Voucher\Voucher;
use App\Providers\NativeServiceProvider\NativeServiceProvider;
use App\Services\AutoTranslationService\AutoTranslationService;
use App\Services\CBIRService\CBIRService;
use App\Services\ChatService\ChatService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Application;
use Illuminate\Http\File;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithPagination;
use Native\Mobile\Facades\Camera;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @mixin Component
 */
class Messages extends Component implements HasActions, HasForms
{
    use CanMarkAsRead, CanValidateFiles, HasPollInterval, InteractsWithActions, InteractsWithForms, WithPagination;

    public $selectedConversation;

    public $currentPage = 1;

    public ?Collection $conversationMessages = null;

    public ?array $data = [];

    public string $searchItem = '';

    public bool $showUpload = false;

    public bool $showEmojiPicker = false;

    public bool $showCamera = false;

    public string $panelId = 'user';

    public ?array $replyTo = null;

    public bool $searchOpen = false;

    public string $searchQuery = '';

    public bool $otherUserIsTyping = false;

    public string $typingUserName = '';

    public function mount(): void
    {
        $this->setPollInterval();
        $this->form->fill();
        $this->conversationMessages = collect();
        if ($this->selectedConversation) {
            $this->loadMessages();
            $this->markAsRead();
        }
    }

    public function pollMessages(): void
    {
        if (! $this->selectedConversation) {
            return;
        }

        $latestId = $this->conversationMessages->pluck('id')->first();

        /** @var Builder $query */
        $query = $this->selectedConversation->messages();

        $polledMessages = $query->where('id', '>', $latestId ?? 0)->latest('id')->get(['*']);
        if ($polledMessages->isNotEmpty()) {
            $this->conversationMessages = collect([
                ...$polledMessages,
                ...$this->conversationMessages,
            ]);

            // Mark new incoming messages as read
            $this->markAsRead();
        }

        // Re-fetch outgoing messages to get updated read_by status
        $unreadOutgoingExists = $this->conversationMessages
            ->where('user_id', auth()->id())
            ->filter(fn ($msg) => empty($msg->read_by) || count(array_filter($msg->read_by, fn ($id) => $id !== auth()->id())) === 0)
            ->isNotEmpty();

        if ($unreadOutgoingExists) {
            $this->conversationMessages = $this->conversationMessages->map(function ($msg) {
                $wasUnread = empty($msg->read_by) || count(array_filter($msg->read_by, fn ($id) => $id !== auth()->id())) === 0;
                if ($wasUnread && $msg->user_id === auth()->id()) {
                    return Message::find($msg->id);
                }

                return $msg;
            });
        }

        // Re-fetch any message whose interactive meta (reactions/starred_by/deleted_by)
        // may have changed by the other participant so the UI stays in sync.
        $interactiveIds = $this->conversationMessages
            ->filter(fn ($msg) => collect($msg->meta ?? [])
                ->only(['reactions', 'starred_by', 'deleted_by'])
                ->filter(fn ($value) => ! empty($value))
                ->isNotEmpty())
            ->pluck('id');

        if ($interactiveIds->isNotEmpty()) {
            $freshById = Message::with('sender')->whereIn('id', $interactiveIds)->get()->keyBy('id');
            $this->conversationMessages = $this->conversationMessages->map(function ($msg) use ($freshById) {
                return $freshById->get($msg->id) ?? $msg;
            });

            $deletedIds = $interactiveIds->diff($freshById->keys());
            if ($deletedIds->isNotEmpty()) {
                $this->conversationMessages = $this->conversationMessages->reject(fn ($msg) => $deletedIds->contains($msg->id))->values();
            }
        }

        // Check if any OTHER participant in this conversation is currently typing
        $inboxId = $this->selectedConversation->id;
        $myId = auth()->id();
        $otherUserIds = collect($this->selectedConversation->user_ids)
            ->reject(fn ($id) => $id === $myId)
            ->values();

        $this->otherUserIsTyping = false;
        $this->typingUserName = '';
        foreach ($otherUserIds as $uid) {
            if (Cache::has("typing_{$inboxId}_{$uid}")) {
                $this->otherUserIsTyping = true;
                $typingUser = $this->selectedConversation->other_users->firstWhere('id', $uid);
                $this->typingUserName = $typingUser?->name ?? '';
                break;
            }
        }
    }

    /**
     * Called each time the message text input changes (via live).
     * Stores a short-lived cache entry so the other side can show "Sedang mengetik...".
     */
    public function setTyping(): void
    {
        if (! $this->selectedConversation) {
            return;
        }

        $inboxId = $this->selectedConversation->id;
        $userId = auth()->id();

        // Keep the key alive for 5 seconds; the poll interval will pick it up
        Cache::put("typing_{$inboxId}_{$userId}", true, now()->addSeconds(5));
    }

    public function loadMessages(): void
    {
        $this->conversationMessages->push(...$this->paginator->items());
        $this->currentPage += 1;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // Forms\Components\SpatieMediaLibraryFileUpload::make('attachments')
                //     ->hiddenLabel()
                //     ->collection(MediaCollectionType::FILAMENT_MESSAGES->value)
                //     ->multiple()
                //     ->panelLayout('grid')
                //     ->maxFiles(config('messages.attachments.max_files'))
                //     ->minFiles(config('messages.attachments.min_files'))
                //     ->maxSize(config('messages.attachments.max_file_size'))
                //     ->minSize(config('messages.attachments.min_file_size'))
                //     ->extraAttributes(['class' => 'messages-attachment-upload'])
                //     ->live(),
                Forms\Components\Actions::make([
                    // Paperclip → cbir-browse-modal (file/folder/drive/cloud)
                    // alpineClickHandler = murni client-side, zero Livewire network call
                    Forms\Components\Actions\Action::make('show_hide_upload')
                        ->hiddenLabel()
                        ->icon('heroicon-o-plus-circle')
                        ->color('gray')
                        ->tooltip(__('Attach Files'))
                        ->alpineClickHandler("window.dispatchEvent(new CustomEvent('cbir-browse-open'))"),
                    // Camera → messages-camera-modal (kamera belakang/depan, video, galeri)
                    // alpineClickHandler = murni client-side, zero Livewire network call
                    Forms\Components\Actions\Action::make('toggle_camera')
                        ->hiddenLabel()
                        ->icon('heroicon-o-camera')
                        ->color('gray')
                        ->tooltip(__('Open Camera'))
                        ->alpineClickHandler("window.dispatchEvent(new CustomEvent('messages-camera-open'))"),
                ]),
                Forms\Components\TextInput::make('message')
                    ->live()
                    ->hiddenLabel()
                    ->placeholder(__('Write a message...'))
                    ->afterStateUpdated(fn () => $this->setTyping()),
            ])->statePath('data');
    }

    public function sendMessage(): void
    {
        $data = $this->form->getState();
        $rawData = $this->form->getRawState();

        try {
            DB::transaction(function () use ($data, $rawData): void {
                $newMessage = $this->selectedConversation->messages()->create([
                    'message' => $data['message'] ?? null,
                    'user_id' => Auth::id(),
                    'read_by' => [Auth::id()],
                    'read_at' => [now()],
                    'notified' => [Auth::id()],
                    'meta' => $this->buildReplyToMeta(),
                ]);

                // Dispatch bot reply if user is not admin
                if (! auth()->user()->hasRole('super_admin')) {
                    SendBotReply::dispatch($newMessage->id)->delay(now()->addSeconds(5));
                }

                $this->conversationMessages->prepend($newMessage);
                collect($rawData['attachments'] ?? [])->each(function ($attachment) use ($newMessage): void {
                    $newMessage->addMedia($attachment)->usingFileName(Str::slug(config('messages.slug'), '_').'_'.Str::random(20).'.'.$attachment->extension())->toMediaCollection(MediaCollectionType::FILAMENT_MESSAGES->value);
                });

                $this->form->fill();

                $this->replyTo = null;

                $this->selectedConversation->updated_at = now();
                $this->selectedConversation->save();

                $this->dispatch('refresh-inbox');
            });
        } catch (\Exception $exception) {
            Notification::make()
                ->title(__('Something went wrong'))
                ->body($exception->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }

    #[Computed()]
    public function paginator(): Paginator
    {
        /** @var Builder $query */
        $query = $this->selectedConversation->messages();

        return $query->latest('id')->paginate(10, ['*'], 'page', $this->currentPage);
    }

    public function downloadAttachment(int $mediaId)
    {
        $media = Media::findOrFail($mediaId);

        return response()->download($media->getPath(), $media->file_name);
    }

    public function validateMessage(): bool
    {
        $rawData = $this->form->getRawState();

        $hasAttachments = ! empty($rawData['attachments']);
        $hasMessage = ! empty($rawData['message']);

        return ! ($hasAttachments || $hasMessage);
    }

    public function deleteConversation()
    {
        if ($this->selectedConversation && in_array(Auth::id(), $this->selectedConversation->user_ids)) {
            $this->selectedConversation->delete();

            Notification::make()
                ->title(__('Conversation deleted'))
                ->success()
                ->send();

            $redirectUrl = MessagesPage::getUrl();

            return $this->redirect($redirectUrl);
        }
    }

    /**
     * Build the meta.reply_to payload for a message being sent right now.
     */
    protected function buildReplyToMeta(): ?array
    {
        if (empty($this->replyTo['id'])) {
            return null;
        }

        return [
            'reply_to' => [
                'id' => (int) $this->replyTo['id'],
                'message' => $this->replyTo['message'] ?? '',
                'sender_name' => $this->replyTo['sender_name'] ?? '',
                'sender_id' => $this->replyTo['sender_id'] ?? null,
            ],
        ];
    }

    public function setReplyTo(int $messageId): void
    {
        $message = $this->conversationMessages->firstWhere('id', $messageId);

        if (! $message) {
            return;
        }

        $this->replyTo = [
            'id' => (int) $message->id,
            'message' => $message->message ?? '',
            'sender_name' => $message->sender?->name ?? '',
            'sender_id' => (int) $message->user_id,
        ];
    }

    public function clearReplyTo(): void
    {
        $this->replyTo = null;
    }

    /**
     * Scroll to and flash-highlight the replied/original message.
     */
    public function highlightMessage(int $messageId): void
    {
        $this->dispatch('chat-highlight-message', messageId: $messageId);
    }

    /**
     * Toggle an emoji reaction on behalf of the current user (mobile parity).
     */
    public function toggleReaction(int $messageId, string $emoji): void
    {
        $message = $this->conversationMessages->firstWhere('id', $messageId);
        if (! $message || ! $this->selectedConversation) {
            return;
        }

        $meta = $message->meta ?? [];
        $reactions = $meta['reactions'] ?? [];
        $userIdStr = (string) auth()->id();

        $userReactions = collect($reactions)->where('user_id', $userIdStr)->values();
        $existingIndex = $userReactions->search(fn ($r) => $r['emoji'] === $emoji);

        if ($existingIndex !== false) {
            $reactions = array_values(array_filter($reactions, fn ($r) => ! ($r['user_id'] === $userIdStr && $r['emoji'] === $emoji)));
        } else {
            $reactions = array_values(array_filter($reactions, fn ($r) => $r['user_id'] !== $userIdStr));
            $reactions[] = ['user_id' => $userIdStr, 'emoji' => $emoji];
        }

        $meta['reactions'] = $reactions;
        $message->meta = $meta;
        $message->save();
    }

    /**
     * Toggle star on a message (mobile parity).
     */
    public function toggleStar(int $messageId): void
    {
        $message = $this->conversationMessages->firstWhere('id', $messageId);
        if (! $message) {
            return;
        }

        $meta = $message->meta ?? [];
        $starredBy = $meta['starred_by'] ?? [];
        $userIdStr = (string) auth()->id();

        if (in_array($userIdStr, $starredBy, true)) {
            $starredBy = array_values(array_filter($starredBy, fn ($id) => $id !== $userIdStr));
        } else {
            $starredBy[] = $userIdStr;
        }

        $meta['starred_by'] = $starredBy;
        $message->meta = $meta;
        $message->save();
    }

    /**
     * Delete a message 'me' (hide only for the current user) or 'everyone' (soft delete).
     */
    public function deleteMessage(int $messageId, string $deleteType = 'me'): void
    {
        $message = $this->conversationMessages->firstWhere('id', $messageId);
        if (! $message || ! $this->selectedConversation) {
            return;
        }

        if ($deleteType === 'everyone') {
            if ((int) $message->user_id !== (int) auth()->id() && ! auth()->user()->hasRole('super_admin')) {
                Notification::make()
                    ->title(__('Tidak diizinkan'))
                    ->danger()
                    ->send();

                return;
            }

            $message->delete();
            $this->conversationMessages = $this->conversationMessages->reject(fn ($m) => $m->id === $messageId)->values();
        } else {
            $meta = $message->meta ?? [];
            $deletedBy = $meta['deleted_by'] ?? [];
            if (! in_array((int) auth()->id(), $deletedBy)) {
                $deletedBy[] = (int) auth()->id();
                $meta['deleted_by'] = $deletedBy;
                $message->meta = $meta;
                $message->save();
            }
        }
    }

    public function forwardMessageAction(): Action
    {
        return Action::make('forwardMessage')
            ->label(__('Teruskan Pesan'))
            ->slideOver()
            ->modalWidth('lg')
            ->modalHeading(__('Teruskan Pesan'))
            ->modalSubmitActionLabel(__('Teruskan'))
            ->form([
                Select::make('target_inbox_id')
                    ->label(__('Pilih Percakapan'))
                    ->searchable()
                    ->placeholder(__('Pilih percakapan tujuan...'))
                    ->options(fn (): array => $this->forwardTargetOptions())
                    ->required(),
            ])
            ->fillForm(fn (array $arguments): ?array => ['target_inbox_id' => null])
            ->action(function (array $arguments, array $data): void {
                $messageId = (int) ($arguments['messageId'] ?? 0);
                $targetInboxId = (int) ($data['target_inbox_id'] ?? 0);

                if (! $messageId || ! $targetInboxId) {
                    return;
                }

                $message = Message::find($messageId);
                $targetInbox = Inbox::find($targetInboxId);

                if (! $message || ! $targetInbox) {
                    Notification::make()
                        ->title(__('Pesan tidak ditemukan'))
                        ->danger()
                        ->send();

                    return;
                }

                $forwardedMessage = Message::create([
                    'inbox_id' => $targetInbox->id,
                    'user_id' => auth()->id(),
                    'message' => $message->message,
                    'meta' => [
                        'forwarded_from' => [
                            'message_id' => $message->id,
                            'sender_id' => $message->user_id,
                            'inbox_id' => $message->inbox_id,
                        ],
                    ],
                ]);

                if ($message->attachments->count() > 0) {
                    foreach ($message->attachments as $attachment) {
                        $forwardedMessage->addMedia($attachment)->toMediaCollection(MediaCollectionType::FILAMENT_MESSAGES->value);
                    }
                }

                if ($targetInbox->id === $this->selectedConversation?->id) {
                    $this->conversationMessages->prepend($forwardedMessage);
                }

                $this->dispatch('refresh-inbox');

                Notification::make()
                    ->title(__('Pesan diteruskan'))
                    ->success()
                    ->send();
            });
    }

    /**
     * Conversations the current user can forward a message into.
     */
    protected function forwardTargetOptions(): array
    {
        if (! auth()->user()) {
            return [];
        }

        $currentInboxId = $this->selectedConversation?->id;

        return auth()->user()
            ->allConversations()
            ->get()
            ->reject(fn ($inbox) => $inbox->id === $currentInboxId)
            ->mapWithKeys(fn ($inbox) => [$inbox->id => $inbox->inbox_title])
            ->all();
    }

    public function messageInfoAction(): Action
    {
        return Action::make('messageInfo')
            ->label(__('Info Pesan'))
            ->slideOver()
            ->modalWidth('md')
            ->modalHeading(__('Info Pesan'))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Tutup'))
            ->form(function (array $arguments): array {
                $messageId = (int) ($arguments['messageId'] ?? 0);
                $message = $this->conversationMessages->firstWhere('id', $messageId);

                if (! $message) {
                    return [];
                }

                $sender = $message->sender?->name ?? __('Tidak dikenal');
                $time = $message->created_at?->setTimezone(config('messages.timezone', 'app.timezone'))->translatedFormat('d M Y, H:i');

                $readBy = collect($message->read_by ?? [])->reject(fn ($id) => (int) $id === (int) auth()->id());
                $readNames = $readBy->isNotEmpty()
                    ? $this->selectedConversation?->other_users->filter(fn ($u) => $readBy->contains((string) $u->id))->pluck('name')->implode(', ')
                    : __('Belum dibaca');

                return [
                    Forms\Components\Placeholder::make('_sender')
                        ->hiddenLabel()
                        ->content(__('Pengirim').': '.$sender),
                    Forms\Components\Placeholder::make('_time')
                        ->hiddenLabel()
                        ->content(__('Waktu').': '.$time),
                    Forms\Components\Placeholder::make('_read')
                        ->hiddenLabel()
                        ->content(__('Dibaca oleh').': '.$readNames),
                ];
            });
    }

    public function translateMessageAction(): Action
    {
        return Action::make('translateMessage')
            ->label(__('Terjemahkan'))
            ->modal()
            ->modalWidth('md')
            ->modalHeading(__('Terjemahan Pesan'))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Tutup'))
            ->form(function (array $arguments): array {
                $messageId = (int) ($arguments['messageId'] ?? 0);
                $message = Message::find($messageId);

                if (! $message || empty($message->message)) {
                    return [
                        Forms\Components\Placeholder::make('_empty')
                            ->hiddenLabel()
                            ->content(__('Tidak ada teks untuk diterjemahkan.')),
                    ];
                }

                $original = $message->message;
                $translated = $this->translateText($original);

                return [
                    Forms\Components\Placeholder::make('_original_label')
                        ->hiddenLabel()
                        ->content(fn () => new HtmlString('<p class="text-xs font-semibold text-gray-500 mb-1">'.__('Asli').'</p>'))
                        ->extraAttributes(['class' => 'p-3 rounded-xl bg-gray-100 dark:bg-white/5']),
                    Forms\Components\Placeholder::make('_original')
                        ->hiddenLabel()
                        ->content(fn () => new HtmlString('<p class="text-sm text-gray-900 dark:text-white whitespace-pre-wrap">'.e($original).'</p>')),
                    Forms\Components\Placeholder::make('_translated_label')
                        ->hiddenLabel()
                        ->content(fn () => new HtmlString('<p class="text-xs font-semibold text-primary-600 mt-3 mb-1">'.__('Terjemahan').'</p>'))
                        ->extraAttributes(['class' => 'p-3 rounded-xl bg-primary-50 dark:bg-primary-500/10']),
                    Forms\Components\Placeholder::make('_translated')
                        ->hiddenLabel()
                        ->content(fn () => new HtmlString('<p class="text-sm text-gray-900 dark:text-white whitespace-pre-wrap">'.e($translated).'</p>'))
                        ->extraAttributes(['class' => 'p-3 rounded-xl bg-primary-50 dark:bg-primary-500/10']),
                ];
            });
    }

    /**
     * Translate a message via Google Translate's public endpoint.
     * Target language follows the current app locale (non-id -> id, id -> en).
     */
    protected function translateText(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return $text;
        }

        $targetLanguage = app()->getLocale() === 'id' ? 'en' : 'id';

        try {
            $response = Http::timeout(5)
                ->withoutVerifying()
                ->get('https://translate.googleapis.com/translate_a/single', [
                    'client' => 'gtx',
                    'sl' => 'auto',
                    'tl' => $targetLanguage,
                    'dt' => 't',
                    'q' => $text,
                ]);

            if ($response->successful()) {
                $segments = $response->json()[0] ?? [];
                $translated = collect($segments)->pluck(0)->implode('');

                if ($translated !== '') {
                    return $translated;
                }
            }
        } catch (\Exception $e) {
            // fall through to original text
        }

        return $text;
    }

    public function rateExperienceAction(): Action
    {
        return Action::make('rateExperience')
            ->label(__('Nilai Pengalaman'))
            ->slideOver()
            ->modalWidth('lg')
            ->modalHeading(__('Nilai Pengalaman Chat'))
            ->modalSubmitActionLabel(__('Kirim Penilaian'))
            ->form([
                Select::make('rating')
                    ->label(__('Rating'))
                    ->options([
                        1 => '1 ⭐',
                        2 => '2 ⭐',
                        3 => '3 ⭐',
                        4 => '4 ⭐',
                        5 => '5 ⭐',
                    ])
                    ->native(false)
                    ->required(),
                Textarea::make('comment')
                    ->label(__('Komentar'))
                    ->rows(4)
                    ->placeholder(__('Tulis komentar Anda (opsional)...'))
                    ->maxLength(500),
            ])
            ->fillForm(function (array $arguments): ?array {
                $existing = $this->selectedConversation?->meta['cs_rating'] ?? null;

                return is_array($existing)
                    ? ['rating' => $existing['rating'] ?? null, 'comment' => $existing['comment'] ?? null]
                    : null;
            })
            ->action(function (array $arguments, array $data): void {
                if (! $this->selectedConversation) {
                    return;
                }

                $meta = $this->selectedConversation->meta ?? [];
                $meta['cs_rating'] = [
                    'user_id' => (int) auth()->id(),
                    'rating' => (int) ($data['rating'] ?? 0),
                    'comment' => $data['comment'] ?? null,
                    'rated_at' => now()->toIso8601String(),
                ];
                $this->selectedConversation->meta = $meta;
                $this->selectedConversation->save();

                Notification::make()
                    ->title(__('Terima kasih atas penilaian Anda!'))
                    ->success()
                    ->send();
            });
    }

    public function shareCatalogAction(): Action
    {
        return Action::make('shareCatalog')
            ->label(__('Bagikan Katalog'))
            ->slideOver()
            ->modalWidth('lg')
            ->modalHeading(__('Bagikan Katalog'))
            ->modalSubmitActionLabel(__('Kirim'))
            ->form([
                Select::make('item_type')
                    ->label(__('Tipe Item'))
                    ->options([
                        'package' => __('Paket'),
                        'product' => __('Produk'),
                    ])
                    ->default('package')
                    ->native(false)
                    ->required()
                    ->live(),
                Select::make('item_id')
                    ->label(__('Pilih Item'))
                    ->searchable()
                    ->preload()
                    ->options(fn (Forms\Get $get): array => $this->catalogItemOptions((string) $get('item_type')))
                    ->required(),
            ])
            ->action(function (array $arguments, array $data): void {
                if (! $this->selectedConversation || empty($data['item_id'])) {
                    return;
                }

                $type = $data['item_type'] ?? 'package';
                $item = $type === 'package'
                    ? Package::find((int) $data['item_id'])
                    : Product::find((int) $data['item_id']);

                if (! $item) {
                    return;
                }

                $newMessage = ChatService::sendContextMessage($this->selectedConversation, [
                    'type' => $type,
                    'id' => (int) $item->id,
                    'name' => $item->name,
                    'price' => $type === 'package' ? ($item->final_price ?? $item->price ?? 0) : ($item->price ?? 0),
                    'image' => $item->image_url,
                    'url' => $type === 'package'
                        ? ($this->tryResourceUrl(PackageResource::class))
                        : ($this->tryResourceUrl(ProductResource::class)),
                    'cs_category' => 'catalog',
                ]);

                $this->conversationMessages->prepend($newMessage);
                $this->selectedConversation->touch();
                $this->dispatch('refresh-inbox');

                Notification::make()
                    ->title(__('Katalog terkirim'))
                    ->success()
                    ->send();
            });
    }

    /**
     * Options for a catalog item type (package/product).
     */
    protected function catalogItemOptions(string $type): array
    {
        $query = $type === 'package' ? Package::query() : Product::query();

        return $query
            ->limit(50)
            ->get()
            ->mapWithKeys(fn ($item) => [
                $item->id => '['.($type === 'package' ? __('Paket') : __('Produk')).'] '.$item->name.' — Rp '.number_format($type === 'package' ? ($item->final_price ?? $item->price ?? 0) : ($item->price ?? 0), 0, ',', '.'),
            ])
            ->all();
    }

    public function shareOrderAction(): Action
    {
        return Action::make('shareOrder')
            ->label(__('Bagikan Pesanan'))
            ->slideOver()
            ->modalWidth('lg')
            ->modalHeading(__('Bagikan Pesanan'))
            ->modalSubmitActionLabel(__('Kirim'))
            ->form([
                Select::make('order_id')
                    ->label(__('Pilih Pesanan'))
                    ->searchable()
                    ->preload()
                    ->placeholder(__('Pilih pesanan...'))
                    ->options(fn (): array => Order::query()
                        ->where('user_id', auth()->id())
                        ->latest()
                        ->limit(50)
                        ->get()
                        ->mapWithKeys(fn ($order) => [
                            $order->id => '#'.$order->order_number.' — '.$order->package?->name ?? $order->product?->name ?? $order->order_number,
                        ])
                        ->all())
                    ->required(),
            ])
            ->action(function (array $arguments, array $data): void {
                if (! $this->selectedConversation || empty($data['order_id'])) {
                    return;
                }

                $order = Order::find((int) $data['order_id']);
                if (! $order || $order->user_id !== auth()->id()) {
                    return;
                }

                $newMessage = ChatService::sendOrderMessage($this->selectedConversation, $order);
                $this->conversationMessages->prepend($newMessage);
                $this->selectedConversation->touch();
                $this->dispatch('refresh-inbox');

                Notification::make()
                    ->title(__('Pesanan terkirim'))
                    ->success()
                    ->send();
            });
    }

    protected function tryResourceUrl(string $resource): ?string
    {
        try {
            return $resource::getUrl(panel: 'user');
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function toggleSearch(): void
    {
        $this->searchOpen = ! $this->searchOpen;

        if (! $this->searchOpen) {
            $this->searchQuery = '';
        }
    }

    #[Computed]
    public function visibleMessages(): Collection
    {
        if (! $this->selectedConversation) {
            return collect();
        }

        if (trim($this->searchQuery) === '') {
            return $this->conversationMessages;
        }

        $term = strtolower(trim($this->searchQuery));

        return $this->conversationMessages->filter(function ($message) use ($term) {
            $text = strtolower((string) $message->message);
            $meta = $message->meta ?? [];

            if (str_contains($text, $term)) {
                return true;
            }

            if (! empty($meta['name']) && str_contains(strtolower((string) $meta['name']), $term)) {
                return true;
            }

            if (! empty($meta['order_number']) && str_contains(strtolower((string) $meta['order_number']), $term)) {
                return true;
            }

            return false;
        })->values();
    }

    public function changeOrderAction(): Action
    {
        return Action::make('changeOrder')
            ->label(__('Ganti Pesanan'))
            ->slideOver()
            ->modalWidth('full')
            ->modalHeading(__('Pilih Katalog Paket Dekorasi Bunga Atau Katalog Bunga'))
            ->modalSubmitAction(false)
            ->form(fn (array $arguments) => [
                Section::make('')
                    ->compact()
                    ->schema([
                        Forms\Components\TextInput::make('search')
                            ->label(__('Cari Visual'))
                            ->placeholder(__('Ketik, ambil foto, atau galeri...'))
                            ->prefixIcon('heroicon-m-magnifying-glass')
                            ->prefixIconColor('gray')
                            ->live(debounce: 500)
                            ->afterStateUpdated(function (Component $livewire, $state, Forms\Set $set) {
                                if (empty($state)) {
                                    session()->forget(['cbir_mixed_results', 'cbir_product_results_ids', 'cbir_search_time', 'cbir_context']);
                                    $set('status_message', null);

                                    return;
                                }

                                $products = Product::query()
                                    ->where('name', 'like', "%{$state}%")
                                    ->orWhere('description', 'like', "%{$state}%")
                                    ->orWhereHas('category', fn ($q) => $q->where('name', 'like', "%{$state}%"))
                                    ->limit(10)
                                    ->get();

                                $packages = Package::query()
                                    ->where('name', 'like', "%{$state}%")
                                    ->orWhere('description', 'like', "%{$state}%")
                                    ->orWhereHas('category', fn ($q) => $q->where('name', 'like', "%{$state}%"))
                                    ->limit(10)
                                    ->get();

                                if ($products->isEmpty() && $packages->isEmpty()) {
                                    session()->forget(['cbir_mixed_results', 'cbir_product_results_ids', 'cbir_search_time', 'cbir_context']);
                                    $set('status_message', __('Tidak ada item yang cocok untuk pencarian teks.'));

                                    return;
                                }

                                $mixedResults = [];

                                foreach ($products as $model) {
                                    $mixedResults[] = [
                                        'type' => 'product',
                                        'similarity' => 100,
                                        'data' => array_merge($model->toArray(), [
                                            'image_url' => $model->image_url,
                                        ]),
                                    ];
                                }

                                foreach ($packages as $model) {
                                    $mixedResults[] = [
                                        'type' => 'package',
                                        'similarity' => 100,
                                        'data' => array_merge($model->toArray(), [
                                            'image_url' => $model->image_url,
                                        ]),
                                    ];
                                }

                                session()->put('cbir_mixed_results', $mixedResults);
                                session()->put('cbir_product_results_ids', collect($mixedResults)->pluck('data.id')->all());
                                session()->put('cbir_search_time', 0);
                                session()->put('cbir_context', 'product');

                                $set('status_message', __('Berhasil menemukan :count hasil teks!', ['count' => count($mixedResults)]));
                            })
                            ->suffixActions([
                                Forms\Components\Actions\Action::make('toggle_camera_search')
                                    ->icon('heroicon-o-camera')
                                    ->color('gray')
                                    ->tooltip(__('Ambil Foto'))
                                    ->action(fn (Forms\Set $set, Forms\Get $get) => $set('show_camera', ! $get('show_camera'))),
                                Forms\Components\Actions\Action::make('toggle_gallery_search')
                                    ->icon('heroicon-o-photo')
                                    ->color('gray')
                                    ->tooltip(__('Pilih Galeri'))
                                    ->action(fn (Forms\Set $set, Forms\Get $get) => $set('show_upload', ! $get('show_upload'))),
                            ]),
                    ]),

                Forms\Components\Grid::make(1)
                    ->schema([
                        TakePicture::make('camera_image')
                            ->hiddenLabel()
                            ->visible(fn (Forms\Get $get) => $get('show_camera'))
                            ->live()
                            ->disk('public')
                            ->directory('cbir-camera')
                            ->registerActions([
                                Forms\Components\Actions\Action::make('manualSearch')
                                    ->label(__('Cari Sekarang'))
                                    ->icon('heroicon-m-arrow-up-tray')
                                    ->color('primary')
                                    ->action(function ($state, Forms\Set $set, CBIRService $cbirService) {
                                        if (! $state) {
                                            return;
                                        }

                                        $this->clearVisualSearch();
                                        $set('status_message', __('Mengunggah & Mencari...'));

                                        // Handle Base64 or Path
                                        if (str_starts_with($state, 'data:image/')) {
                                            $base64Data = preg_replace('#^data:image/\w+;base64,#i', '', $state);
                                            $filename = 'cbir-msg-'.time().'.jpg';
                                            $dir = 'cbir-camera';
                                            if (! is_dir(storage_path('app/public/'.$dir))) {
                                                mkdir(storage_path('app/public/'.$dir), 0755, true);
                                            }
                                            $filePath = storage_path('app/public/'.$dir.'/'.$filename);
                                            file_put_contents($filePath, base64_decode($base64Data));
                                        } else {
                                            $filePath = storage_path('app/public/'.$state);
                                        }

                                        if (! file_exists($filePath)) {
                                            $set('status_message', __('Gagal memproses gambar.'));

                                            return;
                                        }

                                        $file = new File($filePath);
                                        $response = $cbirService->searchByImage($file, 20);

                                        if (isset($response['error']) || ! ($response['success'] ?? false)) {
                                            $set('status_message', $response['message'] ?? __('Server AI Offline.'));

                                            return;
                                        }

                                        $results = $response['results'] ?? [];

                                        // Filter out 0% results
                                        $results = collect($results)->filter(fn ($r) => ($r['similarity'] ?? 0) > 0)->all();

                                        if (! empty($results)) {
                                            $mixedResults = PackageResource::buildCbirMixedResults($results);
                                            session()->put('cbir_mixed_results', $mixedResults);
                                            $set('status_message', __('Berhasil menemukan :count hasil!', ['count' => count($mixedResults)]));
                                        } else {
                                            session()->forget(['cbir_mixed_results', 'cbir_product_results_ids', 'cbir_search_time', 'cbir_context']);
                                            $set('status_message', __('Tidak ada item yang cocok.'));
                                        }
                                    }),
                            ])
                            ->afterStateUpdated(function (Component $livewire, $state, Forms\Set $set, CBIRService $cbirService) {
                                if (! $state) {
                                    return;
                                }

                                // Auto-trigger only for file paths (non-base64)
                                if (str_starts_with($state, 'data:image/')) {
                                    return;
                                }

                                $filePath = storage_path('app/public/'.$state);
                                if (! file_exists($filePath)) {
                                    return;
                                }

                                $file = new File($filePath);
                                $response = $cbirService->searchByImage($file, 20);

                                if (isset($response['error']) || ! ($response['success'] ?? false)) {
                                    $set('status_message', $response['message'] ?? __('Server AI Offline.'));

                                    return;
                                }

                                $results = $response['results'] ?? [];

                                // Filter out 0% results
                                $results = collect($results)->filter(fn ($r) => ($r['similarity'] ?? 0) > 0)->all();

                                if (! empty($results)) {
                                    $mixedResults = PackageResource::buildCbirMixedResults($results);
                                    session()->put('cbir_mixed_results', $mixedResults);
                                    $set('status_message', __('Berhasil menemukan :count hasil!', ['count' => count($mixedResults)]));
                                } else {
                                    session()->forget(['cbir_mixed_results', 'cbir_product_results_ids', 'cbir_search_time', 'cbir_context']);
                                    $set('status_message', __('Tidak ada item yang cocok.'));
                                }
                            }),

                        Forms\Components\FileUpload::make('search_image')
                            ->hiddenLabel()
                            ->image()
                            ->visible(fn (Forms\Get $get) => $get('show_upload'))
                            ->directory('cbir-queries')
                            ->live()
                            ->afterStateUpdated(function (Component $livewire, $state, Forms\Set $set, CBIRService $cbirService) {
                                if (! $state) {
                                    return;
                                }
                                $fileObj = is_array($state) ? reset($state) : $state;
                                $filePath = $fileObj instanceof TemporaryUploadedFile
                                    ? $fileObj->getRealPath()
                                    : storage_path('app/public/'.$fileObj);

                                if (! file_exists($filePath)) {
                                    return;
                                }

                                $file = new File($filePath);
                                $response = $cbirService->searchByImage($file, 20);

                                if (isset($response['error']) || ! ($response['success'] ?? false)) {
                                    $set('status_message', $response['message'] ?? __('Server AI Offline.'));

                                    return;
                                }

                                $results = $response['results'] ?? [];

                                // Filter out 0% results
                                $results = collect($results)->filter(fn ($r) => ($r['similarity'] ?? 0) > 0)->all();

                                if (! empty($results)) {
                                    $mixedResults = PackageResource::buildCbirMixedResults($results);
                                    session()->put('cbir_mixed_results', $mixedResults);
                                    $set('status_message', __('Berhasil menemukan :count hasil!', ['count' => count($mixedResults)]));
                                } else {
                                    session()->forget(['cbir_mixed_results', 'cbir_product_results_ids', 'cbir_search_time', 'cbir_context']);
                                    $set('status_message', __('Item tidak ditemukan.'));
                                }
                            }),

                        Forms\Components\Placeholder::make('status_message')
                            ->label('')
                            ->content(fn (Forms\Set $set, Forms\Get $get) => new HtmlString(
                                '<div class="text-sm">'.e($get('status_message')).'</div>'
                            ))
                            ->visible(fn (Forms\Set $set, Forms\Get $get) => (bool) $get('status_message'))
                            ->extraAttributes(['class' => 'text-center p-3 bg-primary-600 rounded-xl text-white font-medium shadow-md']),

                        // ── CBIR Results Preview ──
                        Forms\Components\ViewField::make('catalog_list')
                            ->view('User.components.cbir-item-card.cbir-item-card')
                            ->viewData([
                                'orderId' => $arguments['orderId'] ?? null,
                            ]),
                    ]),
            ])
            ->extraModalWindowAttributes(['class' => 'bg-gray-50/50 backdrop-blur-3xl']);
    }

    // public function viewOrderAction(): Action
    // {
    //     return Action::make('viewOrder')
    //         ->label(__('Detail Pesanan'))
    //         ->slideOver()
    //         ->modalWidth('2xl')
    //         ->modalHeading(__('Detail Pesanan'))
    //         ->modalSubmitAction(false)
    //         ->modalCancelActionLabel(__('Tutup'))
    //         ->form(function (array $arguments): array {
    //             $orderId = $arguments['orderId'] ?? null;
    //             $order = \App\Models\Order\Order::with(['package.category', 'package.reviews'])->find($orderId);

    //             if (! $order) {
    //                 return [
    //                     Forms\Components\Placeholder::make('_empty')
    //                         ->hiddenLabel()
    //                         ->content(__('Pesanan tidak ditemukan.')),
    //                 ];
    //             }

    //             $item     = $order->package ?? $order->product;
    //             $imageUrl = $item?->image_url ?? '';
    //             $itemName = $item?->name ?? '-';
    //             $woName   = '-';
    //             $category = $order->package?->category?->name ?? '';
    //             $avgRating = $order->package
    //                 ? number_format($order->package->reviews()->avg('rating') ?: 0, 1)
    //                 : '5.0';

    //             $statusColor  = $order->status->getColor();
    //             $payColor     = $order->payment_status->getColor();

    //             $html = '
    //             <div class="space-y-4">
    //                 <!-- Status badges -->
    //                 <div class="flex flex-wrap gap-2 p-3 bg-gray-50 dark:bg-white/5 rounded-2xl">
    //                     <span class="px-3 py-1 rounded-full text-xs font-bold bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200">#'.$order->order_number.'</span>
    //                     <span class="px-3 py-1 rounded-full text-xs font-bold" style="background:#1e40af;color:#fff;">'.$order->status->getLabel().'</span>
    //                     <span class="px-3 py-1 rounded-full text-xs font-bold" style="background:'.($order->payment_status->getColor() === 'success' ? '#15803d' : ($order->payment_status->getColor() === 'danger' ? '#b91c1c' : '#b45309')).';color:#fff;">'.$order->payment_status->getLabel().'</span>
    //                 </div>

    //                 <!-- Item -->
    //                 <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-transparent dark:border-white/10 overflow-hidden">
    //                     '.($imageUrl ? '<img src="'.$imageUrl.'" class="w-full h-36 object-cover">' : '').'
    //                     <div class="p-3 space-y-1">
    //                         '.($category ? '<span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-warning-100 dark:bg-warning-900 text-warning-700 dark:text-warning-300">'.$category.'</span>' : '').'
    //                         <p class="text-xs text-gray-500 dark:text-gray-400 font-bold">'.$woName.'</p>
    //                         <p class="font-bold text-sm text-info-600 dark:text-info-400">'.$itemName.'</p>
    //                         <p class="text-xs text-gray-400">#'.$order->order_number.'</p>
    //                         <div class="pt-2 space-y-1">
    //                             <p class="text-xs text-primary-600 dark:text-primary-400 font-semibold">📅 '.($order->booking_date?->translatedFormat('d M Y') ?? '-').'</p>
    //                             <p class="text-xs font-black text-primary-600 dark:text-primary-400">Rp '.number_format($order->total_price, 2, ',', '.').'</p>
    //                             <p class="text-xs text-gray-400">⭐ '.$avgRating.'</p>
    //                         </div>
    //                     </div>
    //                 </div>

    //                 '.($order->notes ? '
    //                 <!-- Notes -->
    //                 <div class="p-3 bg-gray-50 dark:bg-white/5 rounded-xl">
    //                     <p class="text-xs font-bold text-gray-500 dark:text-gray-400 mb-1">📝 '.__('Catatan').'</p>
    //                     <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">'.e($order->notes).'</p>
    //                 </div>' : '').'
    //             </div>';

    //             return [
    //                 Forms\Components\Placeholder::make('_content')
    //                     ->hiddenLabel()
    //                     ->content(new HtmlString($html)),
    //             ];
    //         });
    // }

    public function manageOrderAction(): Action
    {
        return Action::make('manageOrder')
            ->label(__('Kelola Pesanan'))
            ->slideOver()
            ->modalWidth('4xl')
            ->modalHeading(__('Edit Pesanan'))
            ->modalSubmitActionLabel(__('Simpan'))
            ->form(fn (array $arguments): array => [
                Section::make(__('Status & Keuangan'))
                    ->schema([
                        Select::make('status')
                            ->label(__('Status Pengerjaan'))
                            ->options(OrderStatus::class)
                            ->native(false)
                            ->required(),
                        Select::make('payment_status')
                            ->label(__('Status Pembayaran'))
                            ->options(OrderPaymentStatus::class)
                            ->native(false)
                            ->required(),
                        DatePicker::make('booking_date')
                            ->label(__('Tanggal Acara'))
                            ->native(false)
                            ->prefixIcon('heroicon-o-calendar'),
                        Textarea::make('notes')
                            ->label(__('Catatan'))
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),
            ])
            ->fillForm(function (array $arguments): array {
                $order = Order::find($arguments['orderId'] ?? null);

                return $order ? $order->toArray() : [];
            })
            ->action(function (array $arguments, array $data): void {
                $order = Order::find($arguments['orderId'] ?? null);
                if (! $order) {
                    return;
                }
                $order->update([
                    'status' => $data['status'],
                    'payment_status' => $data['payment_status'],
                    'booking_date' => $data['booking_date'],
                    'notes' => $data['notes'],
                ]);
                Notification::make()
                    ->title(__('Pesanan berhasil diperbarui'))
                    ->success()
                    ->send();
            });
    }

    public function clearVisualSearch(): void
    {
        session()->forget(['cbir_mixed_results', 'cbir_product_results_ids', 'cbir_search_time', 'cbir_context']);
        $this->dispatch('refresh_items');
        $this->dispatch('refresh_catalog');
    }

    /**
     * Open native gallery / file picker (Android/iOS) for CBIR browse modal.
     *
     * @param  'image'|'video'|'all'  $mediaType
     */
    public function openBrowseSource(string $mediaType = 'all', ?string $sourceId = null): void
    {
        if (! NativeServiceProvider::isNativeMobile()) {
            return;
        }

        $mediaType = in_array($mediaType, ['image', 'video', 'all'], true) ? $mediaType : 'all';

        Camera::pickImages($mediaType, false)
            ->id('cbir-browse-'.($sourceId ?? $mediaType))
            ->start();
    }

    public function selectNewItem(string $type, int $id, int $orderId): void
    {
        $item = $type === 'package' ? Package::find($id) : Product::find($id);
        if (! $item) {
            return;
        }

        // Tutup modal changeOrder
        $this->unmountAction();

        // Buka wizard edit order dengan item yang dipilih
        $this->mountAction('editOrderWithItem', [
            'type' => $type,
            'itemId' => $id,
            'orderId' => $orderId,
        ]);
    }

    public function editOrderWithItemAction(): Action
    {
        return Action::make('editOrderWithItem')
            ->label(__('Detail Pesanan Baru'))
            ->slideOver()
            ->modalWidth('screen')
            ->modalHeading(__('Lengkapi Detail Pesanan'))
            ->modalSubmitAction(false)
            ->modalCancelAction(false)
            ->form(function (array $arguments) {
                $type = $arguments['type'] ?? 'package';
                $itemId = $arguments['itemId'] ?? null;
                $orderId = $arguments['orderId'] ?? null;
                $item = $type === 'package' ? Package::find($itemId) : Product::find($itemId);
                $order = Order::find($orderId);

                if (! $item) {
                    return [];
                }

                $finalPrice = $item->price ?? 0;
                if ($item instanceof Package && isset($item->final_price)) {
                    $finalPrice = $item->final_price;
                }

                return [
                    Forms\Components\Placeholder::make('_item_preview')
                        ->hiddenLabel()
                        ->content(new HtmlString(
                            '<div class="flex items-center gap-3 p-3 mb-2 rounded-xl bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10">'
                            .($item->image_url ? '<img src="'.$item->image_url.'" class="w-16 h-16 rounded-lg object-cover flex-shrink-0">' : '')
                            .'<div class="flex-1 min-w-0">'
                            .'<p class="text-[10px] font-semibold mb-0.5 text-warning-600 dark:text-warning-400">'
                            .($type === 'package' ? PackageResource::getModelLabel() : ProductResource::getModelLabel())
                            .'</p>'
                            .'<p class="text-sm font-semibold text-gray-950 dark:text-white truncate">'.$item->name.'</p>'
                            .'<p class="text-xs font-semibold mt-0.5 text-primary-600 dark:text-primary-400">Rp '.number_format($finalPrice, 0, ',', '.').'</p>'
                            .'</div>'
                            .'</div>'
                        )),

                    Forms\Components\Placeholder::make('_wizard_style')
                        ->hiddenLabel()
                        ->content(new HtmlString('<style>ol.fi-fo-wizard-header { pointer-events: none !important; opacity: 0.9; } .fi-fo-wizard-header-step-button { pointer-events: none !important; cursor: default !important; } .fi-fo-wizard > div:last-child > span:nth-child(3), .fi-fo-wizard > div:last-child > span:nth-child(4) { margin-left: auto !important; } .fi-fo-wizard > div:last-child > span:nth-child(2) { display: none !important; }</style><script>document.addEventListener("alpine:init", () => { setTimeout(() => { document.querySelectorAll(".fi-fo-wizard-header-step-button").forEach(btn => btn.disabled = true); }, 100); });</script>')),

                    Forms\Components\Wizard::make([
                        Forms\Components\Wizard\Step::make(__('Detail Acara'))
                            ->icon('heroicon-o-calendar-days')
                            ->schema([
                                Section::make(__('Pilih Waktu & Kebutuhan'))
                                    ->schema([
                                        DatePicker::make('booking_date')
                                            ->label(__('Rencana Tanggal Acara'))
                                            ->required()
                                            ->native(false)
                                            ->default($order?->booking_date)
                                            ->minDate(now())
                                            ->prefixIcon('heroicon-o-calendar-days')
                                            ->columnSpanFull(),
                                        Textarea::make('notes')
                                            ->label(__('Catatan Khusus / Alamat Lokasi'))
                                            ->rows(4)
                                            ->required()
                                            ->default($order?->notes)
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Forms\Components\Wizard\Step::make(__('Info Kontak'))
                            ->icon('heroicon-o-user-circle')
                            ->schema([
                                Section::make(__('Verifikasi Data Anda'))
                                    ->schema([
                                        Forms\Components\TextInput::make('customer_name')
                                            ->label(__('Nama Lengkap'))
                                            ->default(auth()->user()?->name)
                                            ->required(),
                                        Forms\Components\TextInput::make('phone')
                                            ->label(__('Nomor WhatsApp'))
                                            ->default(auth()->user()?->phone)
                                            ->tel()
                                            ->required(),
                                    ])->columns(2),
                            ]),

                        Forms\Components\Wizard\Step::make(__('Voucher & Diskon'))
                            ->icon('heroicon-o-ticket')
                            ->schema([
                                Section::make(__('Pilih Voucher Anda'))
                                    ->description(__('Gunakan voucher yang telah Anda klaim di menu Voucher.'))
                                    ->icon('heroicon-o-ticket')
                                    ->schema([
                                        Select::make('voucher_id')
                                            ->searchable()
                                            ->label(__('Voucher Tersedia'))
                                            ->prefixIcon('heroicon-o-ticket')
                                            ->options(function () use ($finalPrice) {
                                                $user = auth()->user();
                                                if (! $user) {
                                                    return [];
                                                }
                                                $vouchers = Voucher::query()
                                                    ->where('is_active', true)
                                                    ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                                                    ->whereHas('users', fn ($q) => $q->where('users.id', $user->id)->whereNull('user_vouchers.used_at'))
                                                    ->get()
                                                    ->filter(fn ($v) => $v->isValidFor($finalPrice));

                                                return $vouchers->mapWithKeys(function ($v) {
                                                    $amount = $v->discount_type === DiscountType::PERCENTAGE
                                                        ? number_format($v->discount_amount, 2, ',', '.').'%'
                                                        : 'Rp '.number_format($v->discount_amount, 2, ',', '.');

                                                    return [$v->id => $v->code.__(' - Diskon ').$amount];
                                                });
                                            })
                                            ->preload()
                                            ->live()
                                            ->afterStateUpdated(function (Forms\Set $set, ?string $state) use ($finalPrice) {
                                                if (! $state) {
                                                    $set('voucher_discount', 0);
                                                    $set('_voucher_info', null);

                                                    return;
                                                }
                                                $voucher = Voucher::find($state);
                                                if ($voucher && $voucher->isValidFor($finalPrice)) {
                                                    $discount = $voucher->calculateDiscount($finalPrice);
                                                    $set('voucher_discount', $discount);
                                                    $set('_voucher_info', 'valid:'.$voucher->id.':'.$discount.':'.$voucher->description);
                                                } else {
                                                    $set('voucher_id', null);
                                                    $set('voucher_discount', 0);
                                                    $set('_voucher_info', 'invalid');
                                                }
                                            })
                                            ->hint(fn (Forms\Get $get) => match (true) {
                                                str_starts_with((string) $get('_voucher_info'), 'valid:') => __('Voucher Berhasil Dipasang!'),
                                                $get('_voucher_info') === 'invalid' => __('Voucher tidak valid'),
                                                default => null,
                                            })
                                            ->hintIcon(fn (Forms\Get $get) => match (true) {
                                                str_starts_with((string) $get('_voucher_info'), 'valid:') => 'heroicon-m-check-circle',
                                                $get('_voucher_info') === 'invalid' => 'heroicon-m-x-circle',
                                                default => null,
                                            })
                                            ->hintColor(fn (Forms\Get $get) => str_starts_with((string) $get('_voucher_info'), 'valid:') ? 'success' : 'danger')
                                            ->helperText(__('Hanya voucher yang memenuhi syarat minimum belanja yang akan muncul di sini.')),

                                        Forms\Components\Hidden::make('voucher_discount')->default(0),
                                        Forms\Components\Hidden::make('_voucher_info'),

                                        Forms\Components\Placeholder::make('_discount_preview')
                                            ->hiddenLabel()
                                            ->visible(fn (Forms\Get $get) => str_starts_with((string) $get('_voucher_info'), 'valid:'))
                                            ->content(function (Forms\Get $get) use ($finalPrice) {
                                                $discount = (float) $get('voucher_discount');
                                                $final = max(0, $finalPrice - $discount);

                                                return new HtmlString(
                                                    '<div class="flex flex-col gap-2 p-4 bg-success-50 dark:bg-success-950 rounded-xl border border-success-200 dark:border-success-800">'.
                                                        '<div class="flex justify-between text-sm">'.
                                                            '<span class="text-gray-600 dark:text-gray-400">'.__('Harga').'</span>'.
                                                            '<span class="font-semibold">Rp '.number_format($finalPrice, 2, ',', '.').'</span>'.
                                                        '</div>'.
                                                        '<div class="flex justify-between text-sm text-success-600 dark:text-success-400">'.
                                                            '<span>'.__('Diskon Voucher').'</span>'.
                                                            '<span class="font-bold">- Rp '.number_format($discount, 2, ',', '.').'</span>'.
                                                        '</div>'.
                                                        '<div class="flex justify-between text-base font-bold border-t border-success-300 dark:border-success-700 pt-2">'.
                                                            '<span>'.__('Total Bayar').'</span>'.
                                                            '<span class="text-success-700 dark:text-success-300">Rp '.number_format($final, 2, ',', '.').'</span>'.
                                                        '</div>'.
                                                    '</div>'
                                                );
                                            }),
                                    ]),
                            ]),

                        Forms\Components\Wizard\Step::make(__('Konfirmasi'))
                            ->icon('heroicon-o-check-badge')
                            ->schema([
                                Section::make(__('Ringkasan Pembayaran'))
                                    ->schema([
                                        Forms\Components\Placeholder::make('pkg_summary')
                                            ->label(__('Item'))
                                            ->content($item->name),
                                        Forms\Components\Placeholder::make('price_summary')
                                            ->label(__('Total Harga'))
                                            ->content('Rp '.number_format($finalPrice, 0, ',', '.'))
                                            ->extraAttributes(['class' => 'text-primary-600 dark:text-primary-400 font-bold text-2xl']),
                                    ]),
                            ]),
                    ])
                        ->cancelAction(new HtmlString(''))
                        ->previousAction(fn (Forms\Components\Actions\Action $action) => $action->extraAttributes([
                            'style' => 'display: none;',
                            'x-bind:style' => 'isFirstStep() ? \'display: none !important\' : \'\'',
                        ]))
                        ->submitAction(new HtmlString('<div style="display: none;" x-bind:style="{ display: isLastStep() ? \'block\' : \'none\' }"><button type="submit" class="fi-btn fi-btn-size-md fi-btn-color-primary fi-btn-style-solid bg-primary-600 text-white px-4 py-2 rounded-lg font-semibold hover:bg-primary-500">Konfirmasi Pesanan</button></div>')),
                ];
            })
            ->action(function (array $arguments, array $data) {
                $type = $arguments['type'] ?? 'package';
                $itemId = $arguments['itemId'] ?? null;
                $orderId = $arguments['orderId'] ?? null;

                $item = $type === 'package' ? Package::find($itemId) : Product::find($itemId);
                $order = Order::find($orderId);

                if (! $item || ! $order || $order->user_id !== auth()->id()) {
                    return;
                }

                $finalPrice = $item->price ?? 0;
                if ($item instanceof Package && isset($item->final_price)) {
                    $finalPrice = $item->final_price;
                }

                $voucherDiscount = (float) ($data['voucher_discount'] ?? 0);
                $finalPrice = max(0, $finalPrice - $voucherDiscount);

                $updateData = $type === 'package'
                    ? ['package_id' => $item->id, 'product_id' => null]
                    : ['product_id' => $item->id, 'package_id' => null];

                $order->update(array_merge($updateData, [
                    'total_price' => $finalPrice,
                    'booking_date' => $data['booking_date'],
                    'notes' => $data['notes'],
                ]));

                if (! empty($data['phone']) && $data['phone'] !== auth()->user()->phone) {
                    auth()->user()->update(['phone' => $data['phone']]);
                }

                if (! empty($data['voucher_id'])) {
                    auth()->user()->vouchers()->updateExistingPivot($data['voucher_id'], [
                        'order_id' => $order->id,
                        'used_at' => now(),
                    ]);
                }

                $newMessage = $this->selectedConversation->messages()->create([
                    'message' => __('Saya telah mengganti pesanan #:orderNumber menjadi: :name', [
                        'orderNumber' => $order->order_number,
                        'name' => $item->name,
                    ]),
                    'user_id' => auth()->id(),
                    'read_by' => [auth()->id()],
                    'read_at' => [now()],
                    'notified' => [auth()->id()],
                    'meta' => [
                        'type' => $type,
                        'id' => $item->id,
                        'name' => $item->name,
                        'price' => $finalPrice,
                        'image' => $item->image_url,
                        'url' => $type === 'package'
                            ? PackageResource::getUrl()
                            : ProductResource::getUrl(),
                        'is_order' => true,
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'order_status' => $order->status,
                        'payment_status' => $order->fresh()->payment_status->getLabel(),
                    ],
                ]);

                $this->conversationMessages->prepend($newMessage);
                $this->selectedConversation->touch();
                $this->dispatch('refresh-inbox');
                $this->dispatch('chat-box-scroll-to-bottom');

                Notification::make()
                    ->title(__('Pesanan berhasil diperbarui'))
                    ->body(__('Pesanan #:orderNumber telah diganti ke :name', [
                        'orderNumber' => $order->order_number,
                        'name' => $item->name,
                    ]))
                    ->success()
                    ->send();
            });
    }

    public function render(): Application|Factory|View|\Illuminate\View\View
    {
        return view('User.livewire.messages.messages.messages');
    }
}
