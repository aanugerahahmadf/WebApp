@php
    use App\Enums\Messages\MediaCollectionType\MediaCollectionType;
    use App\Support\Chat\MessageBubble\MessageBubble;
@endphp
<!-- Right Section (Chat Box) -->
{{--
    x-data="messageBubble" di elemen paling luar: gelembung aksi pesan milik
    satu panel chat, bukan satu per pesan, dan ia menerima event dari seluruh
    baris pesan di bawahnya. Registrasi Alpine-nya ada di blade halaman
    (Shared/components/message-bubble/message-bubble-script) supaya tidak ikut
    di-render ulang oleh `wire:poll`.
--}}
<div x-data="messageBubble"
    class="w-full h-full overflow-hidden flex flex-col min-h-0">
    @if ($selectedConversation)
        <!-- Chat Header : Start -->
        <div class="grid grid-cols-[var(--cols-default)] lg:grid-cols-[var(--cols-lg)] p-6"
            style="--cols-default: repeat(1, minmax(0, 1fr)); --cols-lg: repeat(1, minmax(0, 1fr));">
            <div style="--col-span-default: 1 / -1;" class="col-[var(--col-span-default)]">
                <div class="flex gap-4 items-center">

                    <a wire:navigate
                        href="{{ \App\Filament\Welcome\Pages\MessagesPage\MessagesPage::getUrl(panel: 'welcome', tenant: filament()->getTenant()) }}"
                        class="p-2 -ml-1 rounded-lg text-gray-500 hover:text-gray-900 hover:bg-gray-100 dark:hover:bg-white/10 transition"
                        title="{{ __('Kembali') }}">
                        <x-filament::icon icon="/images/icons/arrow_left.svg" class="w-5 h-5" />
                    </a>

                    @php
                        $avatar = $selectedConversation->primary_avatar;
                        $alt = urlencode($selectedConversation->inbox_title);
                        $callPhone = $selectedConversation->other_users->first()?->phone
                            ?? $selectedConversation->other_users->first()?->whatsapp;
                        $callPhone = $callPhone ? preg_replace('/[^0-9+]/', '', $callPhone) : null;
                    @endphp

                    <x-filament::avatar src="{{ $avatar }}" alt="{{ $alt }}" size="lg" />

                    <div class="flex-1 overflow-hidden">
                        <div class="flex justify-between items-center gap-2">
                            <p class="text-base font-semibold truncate text-gray-900 dark:text-white">{{ $selectedConversation->inbox_title }}</p>
                        </div>

                        @if ($selectedConversation->title)
                            <p class="text-sm truncate text-gray-600 dark:text-gray-400">
                                {{ $selectedConversation->other_users->pluck('name')->implode(', ') }}</p>
                        @endif
                    </div>

                    <div class="flex items-center gap-1 flex-shrink-0">
                        <button type="button" x-on:click="$dispatch('open-modal', { id: 'welcome-message-call' })"
                                class="p-2 rounded-lg text-green-600 hover:text-green-700 hover:bg-green-50 dark:hover:bg-green-900/20 transition"
                                title="{{ __('Telepon') }}" aria-label="{{ __('Telepon') }}">
                                <x-filament::icon icon="/images/icons/phone.svg" class="w-5 h-5" />
                        </button>
                        <button type="button" x-on:click="$dispatch('open-modal', { id: 'welcome-message-search' })"
                            class="p-2 rounded-lg text-gray-500 hover:text-gray-900 hover:bg-gray-100 dark:hover:bg-white/10 transition"
                            title="{{ __('Cari') }}">
                            <x-filament::icon icon="/images/icons/search.svg" class="w-5 h-5" />
                        </button>

                    </div>
                </div>
            </div>
        </div>
        <x-filament::modal width="xl" id="welcome-message-search">
            <x-slot name="heading">{{ __('Cari pesan...') }}</x-slot>
            <x-filament::input.wrapper prefix-icon="/images/icons/search.svg">
                <x-filament::input type="search" wire:model.live.debounce.300ms="searchQuery"
                    placeholder="{{ __('Cari pesan...') }}" autofocus />
            </x-filament::input.wrapper>
            @if (trim($this->searchQuery) !== '')
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    {{ $this->visibleMessages->count() }} {{ __('hasil') }}
                </p>
            @endif
        </x-filament::modal>
        <x-filament::modal width="lg" id="welcome-message-call">
            <x-slot name="heading">{{ __('Panggilan') }}</x-slot>
            <div class="flex flex-col items-center gap-4 py-8 text-center">
                <x-filament::icon icon="/images/icons/phone.svg" class="h-10 w-10" />
                <h3 class="text-lg font-semibold">{{ $selectedConversation->inbox_title }}</h3>
                <p class="text-sm text-gray-500">{{ __('Panggilan internal aplikasi sedang disiapkan.') }}</p>
                <button type="button" x-on:click="$dispatch('close-modal', { id: 'welcome-message-call' })" class="rounded-lg bg-danger-600 px-4 py-2 text-white">{{ __('Tutup') }}</button>
            </div>
        </x-filament::modal>
        <!-- Chat Header : End -->
        <!-- Chat Box : Start -->
        <div wire:poll.visible.{{ $pollInterval }}="pollMessages()" id="chatContainer"
            class="flex flex-col-reverse flex-1 min-h-0 p-2 sm:p-5 overflow-y-auto">
            @php $visibleMessages = $this->visibleMessages; @endphp
            @foreach ($visibleMessages as $index => $message)
                @php
                    $isMine = $message->user_id === $this->chatUserId();
                    $hasMeta = $message->meta && isset($message->meta['type']);
                    $hasText = !empty($message->message);
                    $hasMedia = $message->getMedia(MediaCollectionType::FILAMENT_MESSAGES->value)?->count() > 0;

                    $meta = $message->meta ?? [];
                    // Normalize legacy messages that stored "\\n" as text, then
                    // let nl2br() below render the resulting line breaks safely.
                    $displayMessage = \App\Support\Chat\BotReplyLocalizer\BotReplyLocalizer::display($message);
                    $displayMessage = str_replace(['\\r\\n', '\\n', '\\r'], ["\r\n", "\n", "\r"], $displayMessage);
                    if ($hasText && is_array($meta) && isset($meta['type'], $meta['name']) && !isset($meta['is_order']) && !isset($meta['is_report'])) {
                        $displayMessage = __('Saya menanyakan tentang :itemType ini: :name', [
                            'itemType' => __($meta['type'] === 'catalog' ? 'Katalog' : 'Paket'),
                            'name' => $meta['name'],
                        ]);
                    }
                    // For order messages, use the original message text from DB (set by ChatService)

                    $createdAt = \Carbon\Carbon::parse($message->created_at)->setTimezone(config('messages.timezone', 'app.timezone'));
                    $date = $createdAt->isToday() ? $createdAt->format('H:i') : $createdAt->format('d/m/y H:i');
                    $isRead = !empty($message->read_by) && count(array_filter($message->read_by, fn($id) => $id !== $this->chatUserId())) > 0;

                    // Mobile-parity message interaction state
                    $deletedForMe = in_array((int) $this->chatUserId(), array_map('intval', $meta['deleted_by'] ?? []));
                    $isForwarded = !empty($meta['forwarded_from']);
                    $replyToMeta = $meta['reply_to'] ?? null;
                    $reactions = is_array($meta['reactions'] ?? null) ? $meta['reactions'] : [];
                    $groupedReactions = collect($reactions)->groupBy('emoji');
                    $myReactionEmojis = collect($reactions)->where('user_id', (string) $this->chatUserId())->pluck('emoji')->all();
                    $isStarred = in_array((string) $this->chatUserId(), array_map('strval', $meta['starred_by'] ?? []));
                    $canDeleteEveryone = $isMine || $this->chatUser()?->hasRole('super_admin');

                    /*
                     * Payload gelembung aksi pesan.
                     *
                     * Daftar aksinya sama persis dengan panel User -- tamu dan
                     * user yang sudah login bicara dengan orang yang sama lewat
                     * thread yang sama, jadi gelembung yang berbeda akan terasa
                     * seperti dua produk. Perbedaannya hanya di siapa yang bisa
                     * menghapus untuk semua, yang di sini tetap membaca peran
                     * lewat chatUser() (Auth kosong di panel Welcome).
                     *
                     * Urutannya mengikuti sheet aksi di aplikasi mobile (balas,
                     * salin, terjemahkan, teruskan, tandai, info, hapus) supaya
                     * web dan mobile terasa sebagai satu produk yang sama.
                     */
                    $bubbleActions = [];

                    if ($deletedForMe) {
                        // Pesan yang dihapus untuk pengguna ini: sisa satu aksi
                        // saja, dan gelembungnya tidak boleh menampilkan deretan
                        // emoji karena tidak ada yang bisa diklik di sana.
                        $bubbleActions[] = MessageBubble::wireAction(
                            'info',
                            __('Info'),
                            '/images/icons/info.svg',
                            'mountAction',
                            ['messageInfo', ['messageId' => (int) $message->id]],
                        );

                        $bubblePayload = MessageBubble::payload((int) $message->id, $bubbleActions, []);
                    } else {
                        if ($hasText) {
                            $bubbleActions[] = MessageBubble::wireAction(
                                'reply',
                                __('Balas'),
                                '/images/icons/uturn_left.svg',
                                'setReplyTo',
                                [(int) $message->id],
                            );

                            // Disalin apa yang tampil, bukan isi mentah kolom
                            // `message`: bot dan kartu pesanan menampilkan
                            // kalimat hasil normalisasi yang berbeda dengan yang
                            // disimpan, dan itu yang dilihat pengguna.
                            $bubbleActions[] = MessageBubble::copyAction(
                                'copy',
                                __('Salin'),
                                '/images/icons/clipboard.svg',
                                $displayMessage,
                            );

                            $bubbleActions[] = MessageBubble::wireAction(
                                'translate',
                                __('Terjemahkan'),
                                '/images/icons/language.svg',
                                'mountAction',
                                ['translateMessage', ['messageId' => (int) $message->id]],
                            );
                        }

                        $bubbleActions[] = MessageBubble::wireAction(
                            'forward',
                            __('Teruskan'),
                            '/images/icons/arrow_right_start.svg',
                            'mountAction',
                            ['forwardMessage', ['messageId' => (int) $message->id]],
                        );

                        $bubbleActions[] = MessageBubble::wireAction(
                            'star',
                            $isStarred ? __('Batal Tandai') : __('Tandai'),
                            $isStarred ? '/images/icons/star.svg' : '/images/icons/star.svg',
                            'toggleStar',
                            [(int) $message->id],
                        );

                        $bubbleActions[] = MessageBubble::wireAction(
                            'info',
                            __('Info'),
                            '/images/icons/info.svg',
                            'mountAction',
                            ['messageInfo', ['messageId' => (int) $message->id]],
                        );

                        $bubbleActions[] = MessageBubble::wireAction(
                            'delete_me',
                            __('Hapus untuk Saya'),
                            '/images/icons/trash.svg',
                            'deleteMessage',
                            [(int) $message->id, 'me'],
                            'danger',
                        );

                        if ($canDeleteEveryone) {
                            $bubbleActions[] = MessageBubble::wireAction(
                                'delete_everyone',
                                __('Hapus untuk Semua'),
                                '/images/icons/trash.svg',
                                'deleteMessage',
                                [(int) $message->id, 'everyone'],
                                'danger',
                            );
                        }

                        $bubblePayload = MessageBubble::payload((int) $message->id, $bubbleActions);
                    }
                @endphp

                {{-- Message Row --}}
                {{--
                    data-msg-bubble menandai baris ini sebagai jangkar gelembung.
                    data-msg-payload dibaca Alpine lewat JSON.parse, jadi atributnya
                    dibungkus tanda kutip tunggal: isi JSON memakai tanda kutip
                    ganda, dan @json sudah mengganti kutip tunggal di dalamnya jadi
                    \u0027 sehingga aman di dalam atribut ini.
                --}}
                <div @class(['flex mb-3', 'justify-end' => $isMine, 'justify-start' => !$isMine])
                     id="msg-{{ $message->id }}"
                     wire:key="{{ $message->id }}"
                     data-msg-bubble
                     data-msg-payload='@json($bubblePayload)'>

                    {{-- Avatar (only for received messages) --}}
                    @if (!$isMine)
                        @php
                            $senderAvatar = $message->sender->avatar_url ?? 'https://ui-avatars.com/api/?name=' . urlencode($message->sender->name);
                        @endphp
                        <div class="flex-shrink-0 self-end mr-2">
                            <x-filament::avatar src="{{ $senderAvatar }}" alt="{{ urlencode($message->sender->name) }}" size="sm" />
                        </div>
                    @endif

                    {{-- Content column --}}
                    <div @class(['flex flex-col min-w-0', 'items-end' => $isMine, 'items-start' => !$isMine])
                         style="max-width: min(calc(100% - 2.5rem), 420px);">

                        {{-- Sender name --}}
                        @if (!$isMine)
                            <p class="text-xs mb-1 text-gray-500 dark:text-gray-400 px-1">{{ $message->sender->name }}</p>
                        @endif

                        {{-- Card (if has meta) --}}
                        @if ($deletedForMe)
                            <div @class([
                                'px-3 py-2 rounded-2xl text-[11px] italic text-gray-400 dark:text-gray-500 bg-gray-100 dark:bg-gray-700/80 flex items-center gap-1.5',
                                'rounded-br-sm' => $isMine,
                                'rounded-bl-sm' => !$isMine,
                            ])>
                                <x-filament::icon icon="/images/icons/trash.svg" class="w-3.5 h-3.5" />
                                <span>{{ __('Anda menghapus pesan ini') }}</span>
                            </div>
                        @else
                        {{-- `isset($meta['id'])`: pesan dengan meta[type] tapi tanpa
                             id (mis. meta poll/stiker) tidak merujuk katalog atau
                             paket, jadi blok gambar ini harus dilewati -- kalau
                             tidak, halaman 500 karena $meta['id'] tidak ada. --}}
                        @if ($hasMeta && isset($meta['id']))
                            @php
                                $itemImage = $meta['image'] ?? null;
                                if ($itemImage) {
                                    $itemImage = \App\Support\AppPlatform\AppPlatform::normalizeUrl($itemImage);
                                }
                                if (!$itemImage || str_contains($itemImage, 'placeholder') || str_contains((string) $itemImage, 'placeholders')) {
                                    $modelClass = $meta['type'] === 'catalog' ? \App\Models\Catalog\Catalog::class : \App\Models\Package\Package::class;
                                    $item = $modelClass::find($meta['id']);
                                    if ($item) {
                                        $freshImage = $item->image_url;
                                        $itemImage = (!str_contains((string) $freshImage, 'placeholder') && !str_contains((string) $freshImage, 'placeholders'))
                                            ? $freshImage
                                            : null;
                                    } else {
                                        $itemImage = null;
                                    }
                                }
                                if (!$itemImage || $itemImage === '' || str_contains((string) $itemImage, 'placeholder') || str_contains((string) $itemImage, 'placeholders')) {
                                    $itemImage = 'https://ui-avatars.com/api/?name=' . urlencode($meta['name']) . '&background=1e293b&color=facc15&size=128';
                                }
                                $isOrderCard       = isset($meta['is_order']) && $meta['is_order'];
                                $isCancellation    = isset($meta['is_cancellation']) && $meta['is_cancellation'];
                                $isPaymentUpdate   = isset($meta['is_payment_update']) && $meta['is_payment_update'];
                                $isRefunded        = isset($meta['is_refunded']) && $meta['is_refunded'];

                                if ($isCancellation) {
                                    $cardBg     = 'background-color:#7f1d1d;';
                                    $labelColor = 'color:#fca5a5;';
                                    $nameColor  = 'color:#f1f5f9;';
                                } else {
                                    $cardBg     = 'background-color:#1e293b;';
                                    $labelColor = 'color:#facc15;';
                                    $nameColor  = 'color:#f1f5f9;';
                                }
                            @endphp
                            <div class="w-full rounded-xl overflow-hidden shadow-md mb-1" style="{{ $cardBg }}">
                                <div class="flex items-center gap-2 p-2">
                                    <img src="{{ $itemImage }}"
                                         class="w-16 h-16 rounded-lg object-cover flex-shrink-0"
                                         alt="{{ $meta['name'] }}"
                                         onerror="this.src='https://ui-avatars.com/api/?name={{ urlencode($meta['name']) }}&background=f3f4f6&color=a1a1aa&size=128'">
                                    <div class="flex-1 min-w-0">
                                        @if($isCancellation)
                                            <p class="text-[9px] font-semibold tracking-tight leading-none mb-0.5" style="color:#fca5a5;">
                                                ❌ {{ __('PESANAN DIBATALKAN') }}
                                            </p>
                                        @elseif($isOrderCard || $isPaymentUpdate)
                                            <p class="text-[9px] font-semibold tracking-tight leading-none mb-0.5" style="color:#facc15;">
                                                {{ __('Pesanan') }} #{{ $meta['order_number'] ?? '-' }}
                                            </p>
                                        @endif
                                        <p class="text-sm font-semibold truncate leading-tight" style="{{ $nameColor }}">{{ $meta['name'] }}</p>
                                        <p class="text-xs font-semibold leading-none mt-0.5" style="{{ $nameColor }}">
                                            Rp {{ number_format($meta['price'], 0, ',', '.') }}
                                        </p>
                                        @if($isCancellation)
                                            <span class="inline-block mt-1 px-1.5 py-0.5 rounded text-[9px] font-semibold leading-none"
                                                  style="background-color:#dc2626;color:#fff;">
                                                {{ $isRefunded ? __('Dana Dikembalikan') : __('Tidak Ada Refund') }}
                                            </span>
                                        @elseif($isOrderCard || $isPaymentUpdate)
                                            @php
                                                // Fetch live payment status dari DB
                                                $livePaymentStatus = null;
                                                if (!empty($meta['order_id'])) {
                                                    $livePaymentStatus = \App\Models\Order\Order::find($meta['order_id'])?->payment_status;
                                                } elseif (!empty($meta['order_number'])) {
                                                    $livePaymentStatus = \App\Models\Order\Order::where('order_number', $meta['order_number'])->value('payment_status');
                                                    if ($livePaymentStatus) {
                                                        $livePaymentStatus = \App\Enums\OrderPaymentStatus\OrderPaymentStatus::tryFrom($livePaymentStatus);
                                                    }
                                                }

                                                if ($livePaymentStatus instanceof \App\Enums\OrderPaymentStatus\OrderPaymentStatus) {
                                                    $payBadgeLabel = $livePaymentStatus->getLabel();
                                                    $payBadgeBg = match($livePaymentStatus) {
                                                        \App\Enums\OrderPaymentStatus\OrderPaymentStatus::PAID    => '#16a34a',
                                                        \App\Enums\OrderPaymentStatus\OrderPaymentStatus::PARTIAL => '#0284c7',
                                                        \App\Enums\OrderPaymentStatus\OrderPaymentStatus::PENDING => '#d97706',
                                                        \App\Enums\OrderPaymentStatus\OrderPaymentStatus::REFUNDED => '#6b7280',
                                                        default => '#dc2626', // unpaid, failed, cancelled
                                                    };
                                                } elseif ($livePaymentStatus === null && !empty($meta['order_id'])) {
                                                    // Order sudah dihapus
                                                    $payBadgeLabel = __('Dibatalkan');
                                                    $payBadgeBg = '#dc2626';
                                                } else {
                                                    // Fallback ke meta snapshot
                                                    $metaPs = strtolower($meta['payment_status'] ?? '');
                                                    $isPaidMeta = str_contains($metaPs, 'paid') || str_contains($metaPs, 'lunas') || str_contains($metaPs, 'berhasil');
                                                    $payBadgeLabel = $isPaidMeta ? __('Sudah Bayar') : __('Belum Bayar');
                                                    $payBadgeBg = $isPaidMeta ? '#16a34a' : '#dc2626';
                                                }
                                            @endphp
                                            <span class="inline-block mt-1 px-1.5 py-0.5 rounded text-[9px] font-semibold leading-none"
                                                  style="background-color:{{ $payBadgeBg }};color:#fff;">
                                                {{ $payBadgeLabel }}
                                            </span>
                                        @endif
                                    </div>
                                    {{-- Inline Details button --}}
                                    @if($isPaymentUpdate)
                                        @php
                                            $payViewOrderId = $meta['order_id'] ?? null;
                                            if (!$payViewOrderId && isset($meta['order_number'])) {
                                                $payViewOrderId = \App\Models\Order\Order::where('order_number', $meta['order_number'])->value('id');
                                            }
                                            $payViewUrl = $payViewOrderId
                                                ? \App\Filament\Welcome\Resources\OrderResource\OrderResource::getUrl('view', ['record' => $payViewOrderId], panel: 'welcome')
                                                : \App\Filament\Welcome\Resources\OrderResource\OrderResource::getUrl('index', panel: 'welcome');
                                        @endphp
                                        <a href="{{ $payViewUrl }}" wire:navigate
                                           class="flex-shrink-0 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors"
                                           style="background-color:#1c1917;color:#facc15;">
                                            {{ __('Lihat Detail') }}
                                        </a>
                                    @elseif(!$isOrderCard && !empty($meta['url']))
                                        <a href="{{ $meta['url'] }}" wire:navigate
                                           class="flex-shrink-0 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors"
                                           style="background-color:#1c1917;color:#facc15;">
                                            {{ __('Details') }}
                                        </a>
                                    @endif
                                </div>
                                @if($isOrderCard || $isCancellation || $isPaymentUpdate)
                                    @php
                                        $viewOrderId = $meta['order_id'] ?? null;
                                        // Fallback: cari order_id dari order_number
                                        if (!$viewOrderId && isset($meta['order_number'])) {
                                            $orderQuery = \App\Models\Order\Order::where('order_number', $meta['order_number']);
                                            $orderQuery->where('user_id', $this->chatUserId());
                                            $viewOrderId = $orderQuery->value('id');
                                        }

                                        // Fetch live order status dari DB
                                        $liveOrder = $viewOrderId ? \App\Models\Order\Order::find($viewOrderId) : null;
                                        $liveStatus = $liveOrder?->status;
                                        $isLiveCancelled = ($liveStatus instanceof \App\Enums\OrderStatus\OrderStatus
                                            && $liveStatus === \App\Enums\OrderStatus\OrderStatus::CANCELLED)
                                            // Jika order_id ada di meta tapi order tidak ditemukan di DB = sudah dihapus = cancelled
                                            || ($viewOrderId && $liveOrder === null);
                                        // Gabungkan: cancelled jika meta is_cancellation ATAU live status cancelled/deleted
                                        // Payment update card = hanya lihat detail, tidak bisa ubah pesanan
                                        $isCancelledCard = $isCancellation || $isLiveCancelled || $isPaymentUpdate;

                                        $viewOrderUrl = $viewOrderId
                                            ? \App\Filament\Welcome\Resources\OrderResource\OrderResource::getUrl('view', ['record' => $viewOrderId], panel: 'welcome')
                                            : \App\Filament\Welcome\Resources\OrderResource\OrderResource::getUrl('index', panel: 'welcome');
                                    @endphp
                                    {{-- Update card background jika live cancelled/deleted --}}
                                    @if($isLiveCancelled && !$isCancellation)
                                        <div class="px-2 pb-1">
                                            <span class="inline-block px-2 py-0.5 rounded text-[9px] font-semibold"
                                                  style="background-color:#dc2626;color:#fff;">
                                                ❌ {{ $liveOrder === null ? __('PESANAN TELAH DIHAPUS') : __('PESANAN DIBATALKAN') }}
                                            </span>
                                        </div>
                                    @endif
                                    <div class="flex">
                                            <a href="{{ $viewOrderUrl }}"
                                               wire:navigate
                                               class="flex-1 py-3 text-sm font-semibold text-center transition-colors {{ $isCancelledCard ? 'rounded-b-xl' : 'rounded-bl-xl border-r border-gray-600' }}"
                                               style="background-color:#334155;color:#f1f5f9;">
                                                {{ __('Lihat Detail') }}
                                            </a>
                                            @if(!$isCancelledCard)
                                                <button wire:click="mountAction('changeOrder', {{ json_encode(['orderId' => $meta['order_id'] ?? null]) }})"
                                                    class="flex-1 py-3 text-sm font-semibold transition-colors rounded-br-xl"
                                                    style="background-color:#facc15;color:#111827;">
                                                    {{ __('Ubah Pesanan') }}
                                                </button>
                                            @endif
                                    </div>
                                @endif
                            </div>
                        @endif

                        {{-- Text / Media bubble --}}
                        @if ($hasText || $hasMedia)
                            @php
                                // Bubble color follows User panel primary color (Yellow #eab308 with dark text)
                                $bubbleStyle = $isMine ? 'background-color:#eab308;color:#1c1917;' : '';
                            @endphp
                            <div @class([
                                'px-3 py-2 rounded-2xl',
                                'rounded-br-sm' => $isMine,
                                'rounded-bl-sm text-gray-900 dark:text-white bg-gray-100 dark:bg-gray-600' => !$isMine,
                            ]) style="{{ $bubbleStyle }}">
                                @if ($hasText)
                                    @if ($replyToMeta)
                                        <button type="button" wire:click="highlightMessage({{ $replyToMeta['id'] ?? 0 }})"
                                            @class([
                                                'block w-full text-left mb-1.5 px-2 py-1 rounded-lg border-l-2 hover:opacity-80 transition',
                                                'bg-black/5 border-gray-300 dark:bg-white/10 dark:border-white/30' => $isMine,
                                                'bg-white/70 border-gray-400 dark:bg-gray-700 dark:border-gray-400' => !$isMine,
                                            ]) title="{{ __('Lihat pesan yang dibalas') }}">
                                            <p class="text-[9px] font-semibold opacity-70 mb-0.5">{{ $replyToMeta['sender_name'] ?? __('Pesan') }}</p>
                                            <p class="text-[11px] truncate opacity-90">{{ $replyToMeta['message'] ?? '' }}</p>
                                        </button>
                                    @endif
                                    @if (isset($meta['sticker']))
                                        <span class="block text-4xl leading-none">{{ $displayMessage }}</span>
                                    @else
                                        <p class="text-sm leading-relaxed">{!! nl2br(e($displayMessage)) !!}</p>
                                    @endif
                                @endif

                                {{-- Kartu poll: pertanyaannya sudah jadi teks pesan,
                                     jadi di sini cukup pilihan + jumlah suara. --}}
                                @if (isset($meta['poll']) && !empty($meta['poll']['options']))
                                    @php
                                        $pollOptions = $meta['poll']['options'];
                                        $myParticipantId = $this->chatParticipantId();
                                        $pollTotalVotes = collect($pollOptions)->sum(fn ($option) => count($option['votes'] ?? []));
                                    @endphp
                                    <div class="mt-2 space-y-1.5">
                                        @foreach ($pollOptions as $optionIndex => $option)
                                            @php
                                                $optionVotes = is_array($option['votes'] ?? null) ? $option['votes'] : [];
                                                $optionVotesCount = count($optionVotes);
                                                $isMyVote = $myParticipantId !== null && in_array((int) $myParticipantId, array_map('intval', $optionVotes), true);
                                                $optionPercent = $pollTotalVotes > 0 ? (int) round($optionVotesCount / $pollTotalVotes * 100) : 0;
                                            @endphp
                                            <button type="button" wire:click="votePoll({{ $message->id }}, {{ $optionIndex }})"
                                                @class([
                                                    'relative w-full overflow-hidden rounded-lg border px-3 py-2 text-left text-sm transition-colors',
                                                    'border-primary-500 bg-primary-50 dark:border-primary-400 dark:bg-danger-500/10' => $isMyVote,
                                                    'border-gray-300 hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-white/5' => ! $isMyVote,
                                                ])>
                                                <span class="absolute inset-y-0 left-0 bg-danger-500/10 dark:bg-primary-400/10" style="width: {{ $optionPercent }}%"></span>
                                                <span class="relative flex items-center justify-between gap-2">
                                                    <span class="truncate text-gray-900 dark:text-white">{{ $option['text'] }}</span>
                                                    <span class="shrink-0 text-xs text-gray-500 dark:text-gray-400">{{ $optionVotesCount }}</span>
                                                </span>
                                            </button>
                                        @endforeach
                                    </div>
                                @endif

                                @if (!empty($meta['is_bot']))
                                    @php
                                        $csCategory = $selectedConversation->meta['cs_category'] ?? null;
                                        $consultationForms = $selectedConversation->meta['consultation_forms'] ?? [];
                                        $hasConsultationForm = !empty($consultationForms[(string) $this->chatUserId()]);

                                        // Laporan yang sudah dikirim pada percakapan ini. Tabel reports
                                        // tidak punya kolom inbox_id, jadi id-nya dicatat di meta
                                        // percakapan oleh aksi csReportForm. Tanpa ini, view tidak punya
                                        // cara tahu bahwa laporan sudah dikirim -- sehingga tombol Unduh
                                        // PDF tidak akan pernah muncul.
                                        $submittedReports = $selectedConversation->meta['reports'] ?? [];
                                        $mySubmittedReport = $submittedReports[(string) $this->chatUserId()] ?? null;
                                        $hasSubmittedReport = !empty($mySubmittedReport['id']);
                                    @endphp
                                    {{-- TTS Voice Button for Bot Messages --}}
                                    <div class="mt-2 flex items-center gap-2">
                                        <button 
                                            type="button" 
                                            id="tts-btn-{{ $message->id }}"
                                            onclick="speakBotMessage({{ $message->id }}, {!! \Illuminate\Support\Js::from($displayMessage) !!})"
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 dark:border-gray-600 px-2.5 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 transition hover:bg-gray-200 dark:hover:bg-gray-700"
                                            title="{{ __('Dengarkan pesan ini') }}"
                                        >
                                            <x-filament::icon id="tts-icon-{{ $message->id }}" icon="/images/icons/speaker.svg" class="w-4 h-4" />
                                            <span id="tts-text-{{ $message->id }}">{{ __('Dengarkan') }}</span>
                                        </button>
                                    </div>
                                    @if ($csCategory === 'decor_consultation' && !empty($meta['consultation_form']))
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            <button type="button" wire:click="mountAction('weddingConsultationForm')"
                                                class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-danger-500">
                                                <x-filament::icon icon="/images/icons/clipboard.svg" class="h-4 w-4" />
                                                {{ $hasConsultationForm ? __('Ubah Formulir') : __('Isi Formulir Kebutuhan Acara') }}
                                            </button>
                                            @if ($hasConsultationForm)
                                                <a href="{{ route('messages.consultation-form.pdf', $selectedConversation) }}"
                                                    class="inline-flex items-center gap-1.5 rounded-lg border border-primary-500 px-3 py-2 text-xs font-semibold text-danger-700 transition hover:bg-danger-50 dark:text-danger-300 dark:hover:bg-primary-900/30">
                                                    <x-filament::icon icon="/images/icons/download.svg" class="h-4 w-4" />
                                                    {{ __('Unduh PDF') }}
                                                </a>
                                            @endif
                                        </div>
                                    @elseif (in_array($csCategory, ['bug_report', 'account_issue', 'order_help', 'payment_issue', 'general_question'], true))
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            <button type="button" wire:click="mountAction('csReportForm')"
                                                class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-danger-500">
                                                <x-filament::icon icon="/images/icons/clipboard.svg" class="h-4 w-4" />
                                                {{ $hasSubmittedReport ? __('Kirim ulang Laporan') : __('Isi Formulir Laporan') }}
                                            </button>
                                            @if ($hasSubmittedReport)
                                                {{-- Link, bukan tombol JS: window.location.assign() dari
                                                     Livewire sering diblokir browser karena tidak ada
                                                     user gesture. Pola yang sama dipakai tombol Unduh PDF
                                                     formulir dekorasi di atas. --}}
                                                <a href="{{ route('welcome.reports.pdf', $mySubmittedReport['id']) }}"
                                                    class="inline-flex items-center gap-1.5 rounded-lg border border-primary-500 px-3 py-2 text-xs font-semibold text-danger-700 transition hover:bg-danger-50 dark:text-danger-300 dark:hover:bg-primary-900/30">
                                                    <x-filament::icon icon="/images/icons/download.svg" class="h-4 w-4" />
                                                    {{ __('Unduh PDF') }}
                                                </a>
                                            @endif
                                        </div>
                                    @endif
                                @endif

                                @if ($hasMedia)
                                    @foreach ($message->getMedia(MediaCollectionType::FILAMENT_MESSAGES->value) as $media)
                                        @php
                                            $mediaMime = $media->mime_type ?? '';
                                            $isImage = str_starts_with($mediaMime, 'image/') || $this->validateImage($media->file_name);
                                            $isAudio = str_starts_with($mediaMime, 'audio/');
                                            $isVideo = str_starts_with($mediaMime, 'video/');
                                        @endphp
                                        @if($isImage)
                                            <div class="mt-1 relative group">
                                                <img src="{{ $media->getUrl() }}"
                                                     class="rounded-lg max-w-full h-auto cursor-pointer shadow-sm"
                                                     wire:click="downloadAttachment({{ $media->id }})" />
                                                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center rounded-lg pointer-events-none">
                                                    <x-filament::icon icon="/images/icons/download.svg" class="w-6 h-6 text-white" />
                                                </div>
                                            </div>
                                        @elseif($isAudio)
                                            <audio src="{{ $media->getUrl() }}" controls playsinline class="mt-1 w-full max-w-full"></audio>
                                        @elseif($isVideo)
                                            <video src="{{ $media->getUrl() }}" controls playsinline class="mt-1 max-w-full rounded-lg"></video>
                                        @else
                                            <div wire:click="downloadAttachment({{ $media->id }})"
                                                @class([
                                                    'flex items-center gap-2 p-2 mt-1 rounded-lg cursor-pointer',
                                                    'bg-gray-200 dark:bg-gray-500' => !$isMine,
                                                    'bg-primary-500 dark:bg-primary-400' => $isMine,
                                                ])>
                                                @php
                                                    $icon = '/images/icons/document.svg';
                                                    if ($this->validateDocument($media->file_name)) $icon = '/images/icons/paperclip.svg';
                                                    if ($this->validateVideo($media->file_name)) $icon = '/images/icons/video.svg';
                                                    if ($this->validateAudio($media->file_name)) $icon = '/images/icons/speaker.svg';
                                                @endphp
                                                <x-filament::icon icon="{{ $icon }}" class="w-4 h-4" />
                                                <p class="text-sm truncate">{{ $media->file_name }}</p>
                                            </div>
                                        @endif
                                    @endforeach
                                @endif
                            </div>
                        @endif
                        @endif {{-- end deletedForMe wrap --}}

                        {{-- Reactions --}}
                        @if (!$deletedForMe && $groupedReactions->isNotEmpty())
                            <div @class(['flex flex-wrap gap-1 mt-0.5 px-1', 'justify-end' => $isMine, 'justify-start' => !$isMine])>
                                @foreach ($groupedReactions as $emoji => $reactedUsers)
                                    @php
                                        $count = $reactedUsers->count();
                                        $isMyReaction = in_array($emoji, $myReactionEmojis, true);
                                    @endphp
                                    <button type="button" wire:click="toggleReaction({{ $message->id }}, '{{ $emoji }}')"
                                        @class([
                                            'rounded-full border px-2 py-0.5 text-xs font-semibold transition',
                                            'bg-yellow-400/40 border-yellow-500 text-yellow-900 dark:text-yellow-100' => $isMyReaction,
                                            'bg-gray-100 dark:bg-gray-700 border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600' => !$isMyReaction,
                                        ])>
                                        {{ $emoji }} {{ $count }}
                                    </button>
                                @endforeach
                            </div>
                        @endif

                        {{-- Timestamp + read status --}}
                        {{--
                            Tombol ellipsis yang tadinya ada di baris waktu sudah
                            dihapus: aksi pesan sekarang muncul sebagai gelembung
                            yang menempel pada pesan itu sendiri saat pesannya
                            ditahan, bukan sebagai menu dropdown yang harus dibuka
                            lewat tombol kecil.
                        --}}
                        <div @class(['flex items-center gap-1 mt-0.5 px-1', 'justify-end' => $isMine, 'justify-start' => !$isMine])>
                            @if ($isForwarded)
                                <span class="text-[9px] text-gray-400 dark:text-gray-500 italic">{{ __('Diteruskan') }}</span>
                                <x-filament::icon icon="/images/icons/arrow_right_start.svg" class="w-3 h-3 text-gray-400 dark:text-gray-500" />
                            @endif
                            @if ($isStarred && !$deletedForMe)
                                <x-filament::icon icon="/images/icons/star.svg" class="w-3 h-3 text-amber-400" />
                            @endif
                            <span class="text-[10px] text-gray-400 dark:text-gray-500">{{ $date }}</span>
                            @if ($isMine)
                                @if ($isRead)
                                    <x-filament::icon icon="/images/icons/chevron_double_right.svg" class="w-3.5 h-3.5 text-blue-400" />
                                    <span class="text-[9px] text-blue-400">{{ __('Dibaca') }}</span>
                                @else
                                    <x-filament::icon icon="/images/icons/check.svg" class="w-3.5 h-3.5 text-gray-400" />
                                    <span class="text-[9px] text-gray-400">{{ __('Terkirim') }}</span>
                                @endif
                            @endif
                        </div>

                    </div>{{-- end content column --}}
                </div>{{-- end message row --}}
                @php
                    $nextMessage = $visibleMessages[$index + 1] ?? null;
                    $nextMessageDate = $nextMessage
                        ? \Carbon\Carbon::parse($nextMessage->created_at)
                            ->setTimezone(config('messages.timezone', 'app.timezone'))
                            ->format('Y-m-d')
                        : null;
                    $currentMessageDate = \Carbon\Carbon::parse($message->created_at)
                        ->setTimezone(config('messages.timezone', 'app.timezone'))
                        ->format('Y-m-d');
                    $showDateBadge = $currentMessageDate !== $nextMessageDate;
                @endphp
                @if ($showDateBadge)
                    <div class="flex justify-center my-4">
                        <x-filament::badge>
                            {{ \Carbon\Carbon::parse($message->created_at)->setTimezone(config('messages.timezone', 'app.timezone'))->translatedFormat('F j, Y') }}
                        </x-filament::badge>
                    </div>
                @endif
            @endforeach
            @if ($this->paginator->hasMorePages())
                <div x-intersect="$wire.loadMessages()">
                    <div class="w-full py-6 text-center text-gray-900 dark:text-gray-200">{{ __('Getting more messages...') }}</div>
                </div>
            @endif
        </div>
        <!-- Chat Box : End -->
        <!-- Chat Input : Start -->
        <div class="w-full px-4 pt-2 pb-4 relative">

            {{-- Modal components for attach & camera --}}
            @include('Welcome.components.cbir-browse-modal.cbir-browse-modal', [
                'isNative' => \App\Support\AppPlatform\AppPlatform::isAnyMobile(),
            ])

            {{-- Hidden file input for cbir-browse-modal (web) — inject files to FilePond attachment --}}
            <input
                id="cbir-browse-file-input"
                type="file"
                accept="image/*,video/*,.pdf,.doc,.docx,.xls,.xlsx,.zip,.rar"
                class="sr-only"
                x-data="{}"
                x-on:change="
                    const file = $event.target.files?.[0];
                    if (!file) return;
                    const pondEl = document.querySelector('.messages-attachment-upload .filepond--root');
                    if (pondEl && window.FilePond) {
                        const inst = window.FilePond.find(pondEl);
                        if (inst) { inst.addFile(file); $event.target.value = ''; return; }
                    }
                    const inp = document.querySelector('.messages-attachment-upload input[type=file]');
                    if (inp) {
                        const dt = new DataTransfer();
                        dt.items.add(file);
                        inp.files = dt.files;
                        inp.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                    $event.target.value = '';
                "
            >
            {{-- Typing Indicator --}}
            @if($this->otherUserIsTyping)
                <div class="flex items-center gap-2 mb-2 px-1" wire:key="typing-indicator">
                    <div class="flex items-center gap-1.5 bg-gray-100 dark:bg-gray-700 rounded-2xl rounded-bl-none px-3 py-2 shadow-sm">
                        <span class="text-xs text-gray-500 dark:text-gray-400 italic">
                            {{ $this->typingUserName ? $this->typingUserName . ' ' . __('sedang mengetik') : __('Sedang mengetik') }}
                        </span>
                        <span class="flex gap-0.5 items-center">
                            <span class="w-1.5 h-1.5 bg-gray-400 dark:bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0ms"></span>
                            <span class="w-1.5 h-1.5 bg-gray-400 dark:bg-gray-400 rounded-full animate-bounce" style="animation-delay: 150ms"></span>
                            <span class="w-1.5 h-1.5 bg-gray-400 dark:bg-gray-400 rounded-full animate-bounce" style="animation-delay: 300ms"></span>
                        </span>
                    </div>
                </div>
            @endif
            {{-- `x-on:submit` menutup menu lampiran sebelum pesan terkirim.
                 Tanpa itu menu bisa tetap terbuka di atas gelembung pesan. --}}
            <form wire:submit="sendMessage()"
                x-on:submit="window.dispatchEvent(new CustomEvent('messages-attach-menu-close'))"
                class="flex items-end justify-between w-full gap-4">
                <div class="w-full max-h-96 overflow-y-auto p-1">
                    @if ($this->replyTo)
                        <div class="flex items-center gap-2 mb-1 px-3 py-2 rounded-xl bg-gray-100 dark:bg-gray-800 border-l-4 border-primary-500">
                            <div class="flex-1 min-w-0">
                                <p class="text-[10px] font-semibold text-primary-600 dark:text-primary-400">
                                    {{ __('Membalas') }} {{ $this->replyTo['sender_name'] ?? '' }}
                                </p>
                                <p class="text-xs truncate text-gray-600 dark:text-gray-300">{{ $this->replyTo['message'] ?? '' }}</p>
                            </div>
                            <button type="button" wire:click="clearReplyTo()"
                                class="p-1 rounded-full text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-700 transition">
                                <x-filament::icon icon="/images/icons/x.svg" class="w-4 h-4" />
                            </button>
                        </div>
                    @endif
                    {{ $this->form }}
                </div>
                <div class="p-1">
                    <x-filament::button 
                        wire:click="sendMessage()" 
                        icon="/images/icons/send.svg"
                        wire:loading.attr="disabled"
                        x-on:click="window.dispatchEvent(new CustomEvent('messages-attach-menu-close'))"
                        class="native-send-btn">
                    </x-filament::button>
                </div>
            </form>
            {{--
                Menu lampiran ala WhatsApp, dipanggil oleh tombol "+" di baris
                composer. Ada di sini, bukan di dalam <form>, karena hanya
                wadah "Chat Input" yang punya `relative` -- itu yang membuat
                menu ini menempel tepat di atas baris composer tanpa perlu
                menghitung tingginya sendiri.
            --}}
            {{--
                Jembatan modal -> komponen percakapan.

                Modal (kamera/audio/kontak) dirender di level HALAMAN, jadi ada di
                luar akar Livewire dan tidak bisa memanggil $wire. Ia
                menembakkan event window `messages-send-pending`; jembatan di
                bawah yang_wait_ sampai lampiran selesai terunggah, lalu
                memanggil $wire.sendMessage().

                Menunggu lampiran itu penting: FilePond mengunggah file lewat
                protocol upload Livewire secara asinkron, jadi state
                `data.attachments` baru terisi setelah upload selesai. Kalau
                pesan dikirim lebih dulu, lampirannya hilang.
            --}}
            <span
                x-data="{
                    messagesUploadingAttachment: false,
                    async messagesAttachFile(file) {
                        if (!file || this.messagesUploadingAttachment) return;
                        this.messagesUploadingAttachment = true;

                        try {
                            // Nama file temporary-nya ikut dikirim. Jadiandainya
                            // state Livewire belum ter-update saat callback
                            // dipanggil, file-nya tetap bisa ditemukan di
                            // folder temporary Livewire.
                            const tmpFilename = await new Promise((resolve, reject) => {
                                $wire.upload('pendingAttachment', file, resolve, reject);
                            });

                            $wire.sendPendingAttachment(tmpFilename);
                        } catch (e) {
                            console.error('[messages] gagal mengunggah lampiran', e);
                        } finally {
                            this.messagesUploadingAttachment = false;
                        }
                    },

                    messagesCreatePoll(question, options) {
                        $wire.createPoll(question, options);
                    },

                    messagesSendSticker(sticker) {
                        $wire.sendSticker(sticker);
                    },
                }"
                x-on:messages-attach-file.window="messagesAttachFile($event.detail.file)"
                x-on:messages-create-poll.window="messagesCreatePoll($event.detail.question, $event.detail.options)"
                x-on:messages-send-sticker.window="messagesSendSticker($event.detail.sticker)"
                class="hidden"
                aria-hidden="true"></span>
            @include('Shared.components.attachment-menu.attachment-menu', ['showSharedActions' => true])

            <x-filament-actions::modals />
        </div>
        <!-- Chat Input : End -->


    @else
        <div class="flex flex-col items-center justify-center h-full p-3">
            <div class="p-3 mb-4 bg-gray-100 rounded-full dark:bg-gray-500/20">
                <x-filament::icon icon="/images/icons/x.svg" class="w-6 h-6 text-gray-500 dark:text-gray-400" />
            </div>
            <p class="text-base text-center text-gray-600 dark:text-gray-400">
                {{ __('No selected conversation') }}
            </p>
        </div>
    @endif

    {{-- Satu panel gelembung untuk seluruh panel chat ini --}}
    @include('Shared.components.message-bubble.message-bubble-panel')
</div>
@script
    <script>
        $wire.on('chat-box-scroll-to-bottom', () => {

            chatContainer = document.getElementById('chatContainer');
            chatContainer.scrollTo({
                top: chatContainer.scrollHeight,
                behavior: 'smooth',
            });

            setTimeout(() => {
                chatContainer.scrollTop = chatContainer.scrollHeight;
            }, 400);
        });

        $wire.on('chat-highlight-message', ({ messageId }) => {
            const chatContainer = document.getElementById('chatContainer');
            const target = document.getElementById('msg-' + messageId);
            if (!chatContainer || !target) return;

            chatContainer.scrollTo({
                top: target.offsetTop - chatContainer.offsetTop,
                behavior: 'smooth',
            });

            target.classList.add('chat-msg-flash');
            setTimeout(() => target.classList.remove('chat-msg-flash'), 2000);
        });

        $wire.on('download-consultation-pdf', ({ url }) => {
            if (url) window.location.assign(url);
        });

        $wire.on('download-report-pdf', ({ url }) => {
            if (url) window.location.assign(url);
        });

        // --- Text-to-Speech (TTS) for Bot Messages ---
        let currentUtterance = null;
        let isSpeaking = false;
        let currentSpeakingMessageId = null;

        /**
         * Speak a bot message using Web Speech API.
         * @param {number} messageId - The message ID for UI state tracking
         * @param {string} text - The text to speak
         */
        function speakBotMessage(messageId, text) {
            // Check browser support
            if (!('speechSynthesis' in window)) {
                alert('{{ __('Fitur suara tidak didukung oleh browser Anda.') }}');
                return;
            }

            // If currently speaking this message, stop it
            if (isSpeaking && currentSpeakingMessageId === messageId) {
                stopSpeaking();
                return;
            }

            // If speaking another message, stop it first
            if (isSpeaking) {
                stopSpeaking();
            }

            // Clean text: remove HTML tags, extra whitespace, and emojis for better TTS
            const cleanText = text
                .replace(/<[^>]*>/g, '')
                .replace(/\s+/g, ' ')
                .trim();

            if (!cleanText) return;

            // Create utterance
            currentUtterance = new SpeechSynthesisUtterance(cleanText);
            currentUtterance.lang = 'id-ID'; // Indonesian language
            currentUtterance.rate = 1.0; // Normal speed
            currentUtterance.pitch = 1.0; // Normal pitch
            currentUtterance.volume = 1.0; // Max volume

            // Try to find an Indonesian voice
            const voices = window.speechSynthesis.getVoices();
            const indonesianVoice = voices.find(voice => voice.lang.startsWith('id'));
            if (indonesianVoice) {
                currentUtterance.voice = indonesianVoice;
            }

            // Event handlers
            currentUtterance.onstart = () => {
                isSpeaking = true;
                currentSpeakingMessageId = messageId;
                updateTtsButton(messageId, true);
            };

            currentUtterance.onend = () => {
                isSpeaking = false;
                currentSpeakingMessageId = null;
                updateTtsButton(messageId, false);
            };

            currentUtterance.onerror = (event) => {
                console.error('TTS Error:', event);
                isSpeaking = false;
                currentSpeakingMessageId = null;
                updateTtsButton(messageId, false);
            };

            // Speak the text
            window.speechSynthesis.speak(currentUtterance);
        }

        /**
         * Stop current speech and reset UI.
         */
        function stopSpeaking() {
            if (window.speechSynthesis.speaking) {
                window.speechSynthesis.cancel();
            }
            isSpeaking = false;
            currentSpeakingMessageId = null;
            // Reset all TTS buttons
            document.querySelectorAll('[id^="tts-btn-"]').forEach(btn => {
                const messageId = btn.id.replace('tts-btn-', '');
                updateTtsButton(parseInt(messageId), false);
            });
        }

        /**
         * Update TTS button UI state.
         * @param {number} messageId - The message ID
         * @param {boolean} speaking - Whether currently speaking
         */
        function updateTtsButton(messageId, speaking) {
            const btn = document.getElementById('tts-btn-' + messageId);
            const icon = document.getElementById('tts-icon-' + messageId);
            const text = document.getElementById('tts-text-' + messageId);

            if (!btn || !icon || !text) return;

            if (speaking) {
                // Change to stop icon
                btn.classList.add('bg-red-100', 'dark:bg-red-900/30', 'border-red-300', 'dark:border-red-700', 'text-danger-700', 'dark:text-primary-400');
                btn.classList.remove('border-gray-300', 'dark:border-gray-600', 'text-gray-700', 'dark:text-gray-300');
                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z" />';
                text.textContent = '{{ __('Berhenti') }}';
            } else {
                // Reset to play icon
                btn.classList.remove('bg-red-100', 'dark:bg-red-900/30', 'border-red-300', 'dark:border-red-700', 'text-danger-700', 'dark:text-primary-400');
                btn.classList.add('border-gray-300', 'dark:border-gray-600', 'text-gray-700', 'dark:text-gray-300');
                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.114 5.636a9 9 0 010 12.728M16.463 8.288a5.25 5.25 0 010 7.424M6.75 8.25l4.72-4.72a.75.75 0 011.28.53v15.88a.75.75 0 01-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.01 9.01 0 012.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75z" />';
                text.textContent = '{{ __('Dengarkan') }}';
            }
        }

        // Load voices (some browsers load voices asynchronously)
        if ('speechSynthesis' in window) {
            window.speechSynthesis.onvoiceschanged = () => {
                // Voices loaded, ready to use
            };
            // Force load voices
            window.speechSynthesis.getVoices();
        }

        // Auto-speak new bot messages when they arrive via Livewire poll
        $wire.on('new-bot-message', ({ messageId, messageText }) => {
            if (messageId && messageText && !isSpeaking) {
                // Small delay to ensure DOM is ready
                setTimeout(() => {
                    speakBotMessage(messageId, messageText);
                }, 500);
            }
        });




    </script>
@endscript
