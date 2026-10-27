<?php

namespace App\Livewire\Admin\Messages\Messages;
use App\Services\CbirResultService\CbirResultService;

use App\Enums\DiscountType\DiscountType;
use App\Enums\Messages\MediaCollectionType\MediaCollectionType;
use App\Enums\OrderPaymentStatus\OrderPaymentStatus;
use App\Enums\OrderStatus\OrderStatus;
use App\Filament\Admin\Pages\MessagesPage\MessagesPage;

use App\Filament\Admin\Resources\CatalogResource\CatalogResource;
use App\Jobs\SendBotReply\SendBotReply;
use App\Livewire\Shared\Traits\CanSendRichMessages\CanSendRichMessages;
use App\Livewire\Admin\Traits\CanMarkAsRead\CanMarkAsRead;
use App\Livewire\Admin\Traits\CanValidateFiles\CanValidateFiles;
use App\Livewire\Admin\Traits\ChatIdentity\ChatIdentity;
use App\Livewire\Admin\Traits\HasPollInterval\HasPollInterval;
use App\Models\Message\Message;
use App\Models\Order\Order;
use App\Models\Package\Package;
use App\Models\Catalog\Catalog;
use App\Models\Voucher\Voucher;
use App\Support\AppPlatform\AppPlatform;
use App\Support\ChatStaff\ChatStaff;
use App\Services\CBIRService\CBIRService;
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
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Application;
use Illuminate\Http\File;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @mixin Component
 */
class Messages extends Component implements HasActions, HasForms
{
    use CanMarkAsRead, CanSendRichMessages, CanValidateFiles, ChatIdentity, HasPollInterval, InteractsWithActions, InteractsWithForms, WithFileUploads, WithPagination;

    public $selectedConversation;

    public $currentPage = 1;

    public ?Collection $conversationMessages = null;

    public ?array $data = [];

    /**
     * Lampiran yang ditangkap di browser (kamera / audio / kontak).
     *
     * Sengaja terpisah dari field FileUpload `attachments`: field itu
     * bergantung pada FilePond, dan di halaman Messages komponen FilePond
     * tidak pernah selesai diinisialisasi -- jadi lampirannya tidak pernah
     * sampai ke server. Tombol "Kirim Audio" tampak berhasil, tapi gelembung
     * pesannya tidak pernah muncul.
     *
     * Diunggah lewat `$wire.upload()` dari jembatan di dalam view, lalu dikirim
     * oleh sendPendingAttachment().
     */
    public ?TemporaryUploadedFile $pendingAttachment = null;

    public string $searchItem = '';

    public bool $showUpload = false;

    public bool $showEmojiPicker = false;

    public bool $showCamera = false;

    public string $panelId = 'admin';

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

        // Identitas chat: users.id staf, BUKAN admins.id dari Auth::id().
        $myId = $this->chatUserId();

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
        $unreadOutgoingExists = $myId !== null && $this->conversationMessages
            ->where('user_id', $myId)
            ->filter(fn ($msg) => empty($msg->read_by) || count(array_filter($msg->read_by, fn ($id) => $id !== $myId)) === 0)
            ->isNotEmpty();

        if ($unreadOutgoingExists) {
            $this->conversationMessages = $this->conversationMessages->map(function ($msg) use ($myId) {
                $wasUnread = empty($msg->read_by) || count(array_filter($msg->read_by, fn ($id) => $id !== $myId)) === 0;
                if ($wasUnread && $msg->user_id === $myId) {
                    return Message::find($msg->id);
                }

                return $msg;
            });
        }

        // Check if any OTHER participant in this conversation is currently typing
        $inboxId = $this->selectedConversation->id;
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
        $userId = $this->chatUserId();

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
                    // Satu tombol "+" untuk semua jenis lampiran. Menu-nya
                    // dirender di Shared/components/attachment-menu, bukan di
                    // sini, karena isinya cuma Alpine murni tanpa perlu tahu
                    // apa pun tentang Livewire.
                    //
                    // `alpineClickHandler` = murni client-side, nol panggilan
                    // jaringan Livewire -- menu harus muncul di bawah satu
                    // frame, bukan setelah satu round-trip.
                    Forms\Components\Actions\Action::make('toggle_attachment_menu')
                        ->hiddenLabel()
                        ->icon('/images/icons/plus_circle.svg')
                        ->color('gray')
                        ->tooltip(__('Attach Files'))
                        ->alpineClickHandler("window.dispatchEvent(new CustomEvent('messages-attach-menu-toggle'))")
                        // PENTING: Filament menambahkan `wire:loading.attr="disabled"`
                        // ke SETIAP <button> miliknya. Karena tombol ini tidak punya
                        // wire:click, Livewire menganggapnya "target semua request",
                        // jadi tombolnya nonaktif selama polling komponen (tiap 2-3
                        // detik) -- praktis tidak pernah bisa diklik, dan menu
                        // lampiran tidak pernah terbuka.
                        //
                        // Memberi nilai null membuat atribut itu tidak dirender,
                        // sehingga tombolnya selalu bisa diklik.
                        ->extraAttributes(['wire:loading.attr' => null]),
                ]),
                /*
                 * Field lampiran yang sebenarnya. UI-nya disembunyikan lewat
                 * class `messages-attachment-upload` (lihat
                 * Shared/components/messages-camera-modal), tetapi FilePond-nya
                 * HARUS tetap dirender: tombol "+" -> menu (Document, Photos &
                 * videos, Camera, Rekam Video) mengisi FilePond ini, dan
                 * sendMessage() membaca state `attachments` di bawah.
                 *
                 * Tanpa field ini, kamera sempat "menangkap" lalu membuang
                 * hasilnya tanpa pesan error apa pun, karena injectFile()
                 * tidak menemukan FilePond untuk diisi.
                 */
                Forms\Components\FileUpload::make('attachments')
                    ->hiddenLabel()
                    ->multiple()
                    ->disk('public')
                    ->directory('messages-attachments')
                    ->maxFiles(config('messages.attachments.max_files'))
                    ->maxSize(config('messages.attachments.max_file_size'))
                    ->extraAttributes(['class' => 'messages-attachment-upload'])
                    ->live(),
                Forms\Components\TextInput::make('message')
                    ->live()
                    ->hiddenLabel()
                    ->placeholder(__('Write a message...'))
                    ->afterStateUpdated(fn () => $this->setTyping()),
            ])->statePath('data');
    }

    /**
     * Kirim pesan yang lampirannya baru saja diunggah dari browser.
     *
     * Dipanggil dari jembatan di dalam view, tepat setelah `$wire.upload()`
     * selesai, supaya lampirannya sudah ada di server saat pesan dibuat.
     */
    public function sendPendingAttachment(string $tmpFilename): void
    {
        $this->pendingAttachment = TemporaryUploadedFile::createFromLivewire($tmpFilename);

        $this->sendMessage();
    }
    public function sendMessage(): void
    {
        $chatUserId = $this->chatUserId();

        if (! $chatUserId) {
            Notification::make()
                ->title(__('Chat belum siap'))
                ->body(__('Akun chat belum tertaut ke staf. Jalankan php artisan admin:grant-storefront lalu muat ulang halaman ini.'))
                ->warning()
                ->send();

            return;
        }

        /*
         * getState() menyimpan lampiran ke disk `public` (lihat
         * ->disk('public')->directory('messages-attachments') pada field
         * `attachments`) dan mengembalikan PATH-nya, bukan objek file.
         */
        $data = $this->form->getState();

        // Lampiran yang ditangkap di browser (kamera / audio / kontak).
        $pending = $this->pendingAttachment;
        $this->pendingAttachment = null;

        try {
            DB::transaction(function () use ($data, $pending, $chatUserId): void {
                $newMessage = $this->selectedConversation->messages()->create([
                    'message' => $data['message'] ?? null,
                    'user_id' => $chatUserId,
                    'read_by' => [$chatUserId],
                    'read_at' => [now()],
                    'notified' => [$chatUserId],
                ]);

                // Pengirim dari panel admin SELALU sisi staf (percakapan
                // berjalan sebagai identitas belanja yang tertaut), jadi bot
                // balasan otomatis tidak dipicu -- aturan lama "pengirim admin
                // tidak memicu bot" tetap berlaku lewat isStaffMixed().
                if (! ChatStaff::isStaffMixed(auth()->user())) {
                    SendBotReply::dispatch($newMessage->id);
                }

                $this->conversationMessages->prepend($newMessage);

                /*
                 * `attachments` berisi path relatif di disk `public` setelah
                 * form di-dehydrate. addMedia() butuh file nyata, jadi path
                 * itu diubah dulu menjadi path absolut. File sumber dihapus
                 * setelah disalin ke media library supaya
                 * storage/app/public/messages-attachments tidak menumpuk.
                 */
                $attachments = collect($data['attachments'] ?? [])
                    ->filter(fn ($attachment) => is_string($attachment) && $attachment !== '');

                if ($pending) {
                    $attachments->push($pending->store('messages-attachments', 'public'));
                }

                $attachments->each(function (string $attachment) use ($newMessage): void {
                        $path = Storage::disk('public')->path($attachment);

                        if (! is_file($path)) {
                            return;
                        }

                        $newMessage
                            ->addMedia($path)
                            ->usingFileName(Str::slug(config('messages.slug'), '_').'_'.Str::random(20).'.'.pathinfo($path, PATHINFO_EXTENSION))
                            ->toMediaCollection(MediaCollectionType::FILAMENT_MESSAGES->value);

                        @unlink($path);
                    });

                $this->form->fill();

                $this->selectedConversation->updated_at = now();
                $this->selectedConversation->save();

                $this->dispatch('refresh-inbox');
            });
        } catch (\Exception $exception) {
            report($exception);

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

    /**
     * Toggle emoji reaction.
     *
     * Bentuknya sama persis dengan App\Livewire\User\Messages\Messages\Messages
     * karena keduanya menulis ke kolom meta yang sama pada tabel fm_messages:
     * satu pengguna hanya boleh punya satu emoji per pesan. Menulis ulang
     * bentuknya di sini (bukan memanggil method komponen User) disengaja --
     * dua komponen Livewire tidak bisa saling memanggil, dan menyalin
     * enumerator 15 baris lebih murah daripada menyatukan kedua panel jadi satu
     * komponen.
     */
    public function toggleReaction(int $messageId, string $emoji): void
    {
        $message = $this->conversationMessages->firstWhere('id', $messageId);

        if (! $message || ! $this->selectedConversation) {
            return;
        }

        $meta = $message->meta ?? [];
        $reactions = $meta['reactions'] ?? [];
        $userIdStr = (string) $this->chatUserId();

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
     * Toggle star. Sama seperti toggleReaction: satu admin satu tanda, dan
     * panel User melihat hasil yang sama karena meta-nya berbagi.
     */
    public function toggleStar(int $messageId): void
    {
        $message = $this->conversationMessages->firstWhere('id', $messageId);

        if (! $message) {
            return;
        }

        $meta = $message->meta ?? [];
        $starredBy = $meta['starred_by'] ?? [];
        $userIdStr = (string) $this->chatUserId();

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
     * Hapus pesan secara permanen.
     *
     * Berbeda dari panel User yang punya dua mode ("untuk saya" dan "untuk
     * semua"), panel admin hanya punya satu: administrator memang bekerja di
     * satu sisi percakapan dengan pelanggan, jadi mengosongkan pesan dari kolom
     * adalah tindakan yang wajar. Memakai hard delete supaya tidak pernah ada
     * pesan yang hilang di layar admin tapi masih ada di layar user -- kondisi
     * yang harus ditelusuri.
     */
    public function deleteMessage(int $messageId): void
    {
        $message = $this->conversationMessages->firstWhere('id', $messageId);

        if (! $message || ! $this->selectedConversation) {
            return;
        }

        $message->delete();

        $this->conversationMessages = $this->conversationMessages->reject(fn ($m) => $m->id === $messageId)->values();
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
        if ($this->selectedConversation && in_array($this->chatUserId(), $this->selectedConversation->user_ids)) {
            $this->selectedConversation->delete();

            Notification::make()
                ->title(__('Conversation deleted'))
                ->success()
                ->send();

            $redirectUrl = MessagesPage::getUrl();

            return $this->redirect($redirectUrl);
        }
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
                            ->prefixIcon('/images/icons/search.svg')
                            ->prefixIconColor('gray')
                            ->live(debounce: 500)
                            ->afterStateUpdated(function (Component $livewire, $state, Forms\Set $set) {
                                if (empty($state)) {
                                    session()->forget(['cbir_mixed_results', 'cbir_catalog_results_ids', 'cbir_search_time', 'cbir_context']);
                                    $set('status_message', null);

                                    return;
                                }

                                $catalogs = Catalog::query()
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

                                if ($catalogs->isEmpty() && $packages->isEmpty()) {
                                    session()->forget(['cbir_mixed_results', 'cbir_catalog_results_ids', 'cbir_search_time', 'cbir_context']);
                                    $set('status_message', __('Tidak ada item yang cocok untuk pencarian teks.'));

                                    return;
                                }

                                $mixedResults = [];

                                foreach ($catalogs as $model) {
                                    $mixedResults[] = [
                                        'type' => 'catalog',
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
                                session()->put('cbir_catalog_results_ids', collect($mixedResults)->pluck('data.id')->all());
                                session()->put('cbir_search_time', 0);
                                session()->put('cbir_context', 'catalog');

                                $set('status_message', __('Berhasil menemukan :count hasil teks!', ['count' => count($mixedResults)]));
                            })
                            ->suffixActions([
                                Forms\Components\Actions\Action::make('toggle_camera_search')
                                    ->icon('/images/icons/camera.svg')
                                    ->color('gray')
                                    ->tooltip(__('Ambil Foto'))
                                    ->action(fn (Forms\Set $set, Forms\Get $get) => $set('show_camera', ! $get('show_camera'))),
                                Forms\Components\Actions\Action::make('toggle_gallery_search')
                                    ->icon('/images/icons/photo.svg')
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
                                    ->icon('/images/icons/upload.svg')
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
                                            $mixedResults = CbirResultService::buildCbirMixedResults($results);
                                            session()->put('cbir_mixed_results', $mixedResults);
                                            $set('status_message', __('Berhasil menemukan :count hasil!', ['count' => count($mixedResults)]));
                                        } else {
                                            session()->forget(['cbir_mixed_results', 'cbir_catalog_results_ids', 'cbir_search_time', 'cbir_context']);
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
                                    $mixedResults = CbirResultService::buildCbirMixedResults($results);
                                    session()->put('cbir_mixed_results', $mixedResults);
                                    $set('status_message', __('Berhasil menemukan :count hasil!', ['count' => count($mixedResults)]));
                                } else {
                                    session()->forget(['cbir_mixed_results', 'cbir_catalog_results_ids', 'cbir_search_time', 'cbir_context']);
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
                                    $mixedResults = CbirResultService::buildCbirMixedResults($results);
                                    session()->put('cbir_mixed_results', $mixedResults);
                                    $set('status_message', __('Berhasil menemukan :count hasil!', ['count' => count($mixedResults)]));
                                } else {
                                    session()->forget(['cbir_mixed_results', 'cbir_catalog_results_ids', 'cbir_search_time', 'cbir_context']);
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
                            ->view('Admin.components.cbir-item-card.cbir-item-card')
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

    //             $item     = $order->package ?? $order->catalog;
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
                            ->prefixIcon('/images/icons/calendar.svg'),
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
        session()->forget(['cbir_mixed_results', 'cbir_catalog_results_ids', 'cbir_search_time', 'cbir_context']);
        $this->dispatch('refresh_items');
        $this->dispatch('refresh_catalog');
    }

    /**
     * Open the gallery / file picker for the CBIR browse modal.
     *
     * The Capacitor shell is a real browser, so this just triggers the hidden
     * `<input type="file">` the browse modal renders — the same path the website
     * uses. There is no on-device picker API to call from PHP.
     *
     * @param  'image'|'video'|'all'  $mediaType
     */
    public function openBrowseSource(string $mediaType = 'all', ?string $sourceId = null): void
    {
        $this->dispatch('capacitor-camera-open-input', mode: $mediaType === 'image' ? 'photo-back' : 'gallery');
    }

    public function selectNewItem(string $type, int $id, int $orderId): void
    {
        $item = $type === 'package' ? Package::find($id) : Catalog::find($id);
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
                $item = $type === 'package' ? Package::find($itemId) : Catalog::find($itemId);
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
                            .($type === 'package' ? __('Paket') : CatalogResource::getModelLabel())
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
                            ->icon('/images/icons/calendar_days.svg')
                            ->schema([
                                Section::make(__('Pilih Waktu & Kebutuhan'))
                                    ->schema([
                                        DatePicker::make('booking_date')
                                            ->label(__('Rencana Tanggal Acara'))
                                            ->required()
                                            ->native(false)
                                            ->default($order?->booking_date)
                                            ->minDate(now())
                                            ->prefixIcon('/images/icons/calendar_days.svg')
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
                            ->icon('/images/icons/user_circle.svg')
                            ->schema([
                                Section::make(__('Verifikasi Data Anda'))
                                    ->schema([
                                        Forms\Components\TextInput::make('customer_name')
                                            ->label(__('Nama Lengkap'))
                                            ->default($order?->user?->full_name)
                                            ->required(),
                                        Forms\Components\TextInput::make('phone')
                                            ->label(__('Nomor WhatsApp'))
                                            ->default($order?->user?->phone)
                                            ->tel()
                                            ->required(),
                                    ])->columns(2),
                            ]),

                        Forms\Components\Wizard\Step::make(__('Voucher & Diskon'))
                            ->icon('/images/icons/ticket.svg')
                            ->schema([
                                Section::make(__('Pilih Voucher Anda'))
                                    ->description(__('Gunakan voucher yang telah Anda klaim di menu Voucher.'))
                                    ->icon('/images/icons/ticket.svg')
                                    ->schema([
                                        Select::make('voucher_id')
                                            ->searchable()
                                            ->label(__('Voucher Tersedia'))
                                            ->prefixIcon('/images/icons/ticket.svg')
                                            ->options(function () use ($finalPrice, $order) {
                                                // Voucher milik PEMILIK pesanan (pelanggan),
                                                // bukan milik admin yang sedang bertindak.
                                                $user = $order?->user;
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
                                                str_starts_with((string) $get('_voucher_info'), 'valid:') => '/images/icons/check_circle.svg',
                                                $get('_voucher_info') === 'invalid' => '/images/icons/x_circle.svg',
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
                            ->icon('/images/icons/check_badge.svg')
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
                $chatUserId = $this->chatUserId();
                $type = $arguments['type'] ?? 'package';
                $itemId = $arguments['itemId'] ?? null;
                $orderId = $arguments['orderId'] ?? null;

                $item = $type === 'package' ? Package::find($itemId) : Catalog::find($itemId);
                $order = Order::find($orderId);

                // Admin mengganti pesanan ATAS NAMA pelanggan: yang diubah
                // adalah data PEMILIK pesanan. Dulu di sini
                // `$order->user_id !== auth()->id()` -- di panel admin
                // `auth()->id()` adalah admins.id sehingga tidak pernah cocok
                // dan wizard ini selalu buntu. Sekarang: pesanan harus milik
                // peserta percakapan ini, dan identitas staf harus ada.
                if (
                    ! $item
                    || ! $order
                    || ! $chatUserId
                    || ! in_array($order->user_id, $this->selectedConversation?->user_ids ?? [])
                ) {
                    return;
                }

                $finalPrice = $item->price ?? 0;
                if ($item instanceof Package && isset($item->final_price)) {
                    $finalPrice = $item->final_price;
                }

                $voucherDiscount = (float) ($data['voucher_discount'] ?? 0);
                $finalPrice = max(0, $finalPrice - $voucherDiscount);

                $updateData = $type === 'package'
                    ? ['package_id' => $item->id, 'catalog_id' => null]
                    : ['catalog_id' => $item->id, 'package_id' => null];

                $order->update(array_merge($updateData, [
                    'total_price' => $finalPrice,
                    'booking_date' => $data['booking_date'],
                    'notes' => $data['notes'],
                ]));

                $customer = $order->user;

                if ($customer && ! empty($data['phone']) && $data['phone'] !== $customer->phone) {
                    $customer->update(['phone' => $data['phone']]);
                }

                if ($customer && ! empty($data['voucher_id'])) {
                    $customer->vouchers()->updateExistingPivot($data['voucher_id'], [
                        'order_id' => $order->id,
                        'used_at' => now(),
                    ]);
                }

                $newMessage = $this->selectedConversation->messages()->create([
                    'message' => __('Saya telah mengganti pesanan #:orderNumber menjadi: :name', [
                        'orderNumber' => $order->order_number,
                        'name' => $item->name,
                    ]),
                    'user_id' => $chatUserId,
                    'read_by' => [$chatUserId],
                    'read_at' => [now()],
                    'notified' => [$chatUserId],
                    'meta' => [
                        'type' => $type,
                        'id' => $item->id,
                        'name' => $item->name,
                        'price' => $finalPrice,
                        'image' => $item->image_url,
                        'url' => $type === 'package'
                            ? CatalogResource::getUrl()
                            : CatalogResource::getUrl(),
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
        if (! $this->selectedConversation || trim($this->searchQuery) === '') {
            return $this->conversationMessages ?? collect();
        }

        $term = strtolower(trim($this->searchQuery));

        return ($this->conversationMessages ?? collect())->filter(function ($message) use ($term) {
            $text = strtolower((string) $message->message);
            $meta = $message->meta ?? [];

            return str_contains($text, $term)
                || (! empty($meta['name']) && str_contains(strtolower((string) $meta['name']), $term))
                || (! empty($meta['order_number']) && str_contains(strtolower((string) $meta['order_number']), $term));
        })->values();
    }

    public function render(): Application|Factory|View|\Illuminate\View\View
    {
        return view('Admin.livewire.messages.messages.messages');
    }
}
