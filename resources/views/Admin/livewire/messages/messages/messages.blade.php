@php
    use App\Enums\Messages\MediaCollectionType\MediaCollectionType;
    use App\Support\Chat\MessageBubble\MessageBubble;
@endphp
@props(['selectedConversation'])
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
                    <x-filament::icon-button
                        icon="/images/icons/chevron_left.svg"
                        color="gray"
                        size="md"
                        class="-ms-2"
                        href="{{ \App\Filament\Admin\Pages\MessagesPage\MessagesPage::getUrl() }}"
                        tag="a"
                        wire:navigate
                    />


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
                    <button type="button" x-on:click="$dispatch('open-modal', { id: 'admin-message-call' })"
                            class="rounded-lg p-2 text-green-600 transition hover:bg-green-50 hover:text-green-700 dark:hover:bg-green-900/20"
                            title="{{ __('Telepon Internet') }}" aria-label="{{ __('Telepon Internet') }}">
                            <x-filament::icon icon="/images/icons/phone.svg" class="h-5 w-5" />
                    </button>
                    <button type="button" x-on:click="$dispatch('open-modal', { id: 'admin-message-search' })"
                        class="rounded-lg p-2 text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 dark:hover:bg-white/10"
                        title="{{ __('Cari') }}">
                        <x-filament::icon icon="/images/icons/search.svg" class="h-5 w-5" />
                    </button>
                </div>
            </div>
        </div>
        <x-filament::modal width="xl" id="admin-message-search">
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
        <x-filament::modal width="lg" id="admin-message-call">
            <x-slot name="heading">{{ __('Panggilan') }}</x-slot>
            <div class="flex flex-col items-center gap-4 py-8 text-center">
                <x-filament::icon icon="/images/icons/phone.svg" class="h-10 w-10" />
                <h3 class="text-lg font-semibold">{{ $selectedConversation->inbox_title }}</h3>
                <p class="text-sm text-gray-500">{{ __('Panggilan internal sedang disiapkan.') }}</p>
                <button type="button" x-on:click="$dispatch('close-modal', { id: 'admin-message-call' })" class="rounded-lg bg-danger-600 px-4 py-2 text-white">{{ __('Tutup') }}</button>
            </div>
        </x-filament::modal>
        <!-- Chat Header : End -->
        <!-- Chat Box : Start -->
        <div wire:poll.visible.{{ $pollInterval }}="pollMessages()" id="chatContainer"
            class="flex flex-col-reverse flex-1 min-h-0 p-2 sm:p-5 overflow-y-auto">
            @foreach ($conversationMessages as $index => $message)
                @php
                    // Identitas chat panel admin = users.id staf (ChatStaff),
                    // bukan admins.id dari auth()->id().
                    $chatUserId = $this->chatUserId();
                    $isMine = $message->user_id === $chatUserId;
                    $hasMeta = $message->meta && isset($message->meta['type']);
                    $hasText = !empty($message->message);
                    $hasMedia = $message->getMedia(MediaCollectionType::FILAMENT_MESSAGES->value)?->count() > 0;

                    $meta = $message->meta ?? [];
                    // Normalize legacy messages that stored "\\n" as text, then
                    // let nl2br() below render the resulting line breaks safely.
                    $displayMessage = \App\Support\Chat\BotReplyLocalizer\BotReplyLocalizer::display($message);
                    $displayMessage = str_replace(['\\r\\n', '\\n', '\\r'], ["\r\n", "\n", "\r"], $displayMessage);
                    if ($hasText && is_array($meta) && isset($meta['type'], $meta['name']) && !isset($meta['is_order'])) {
                        $displayMessage = __('Saya menanyakan tentang :itemType ini: :name', [
                            'itemType' => __($meta['type'] === 'catalog' ? 'Katalog' : 'Paket'),
                            'name' => $meta['name'],
                        ]);
                    }
                    // For order messages, use the original message text from DB (set by ChatService)

                    $createdAt = \Carbon\Carbon::parse($message->created_at)->setTimezone(config('messages.timezone', 'app.timezone'));
                    $date = $createdAt->isToday() ? $createdAt->format('H:i') : $createdAt->format('d/m/y H:i');
                    $isRead = !empty($message->read_by) && count(array_filter($message->read_by, fn($id) => $id !== $chatUserId)) > 0;

                    // Reaksi + tandai, dibaca dari meta yang sama dengan panel
                    // User: satu tabel pesan untuk dua panel, jadi formatnya
                    // hanya boleh punya satu pembaca di satu tempat.
                    $reactions = is_array($meta['reactions'] ?? null) ? $meta['reactions'] : [];
                    $groupedReactions = collect($reactions)->groupBy('emoji');
                    $myReactionEmojis = collect($reactions)->where('user_id', (string) $chatUserId)->pluck('emoji')->all();
                    $isStarred = in_array((string) $chatUserId, array_map('strval', $meta['starred_by'] ?? []));

                    /*
                     * Payload gelembung aksi pesan.
                     *
                     * Admin tidak punya "hapus untuk saya" dan tidak punya aksi
                     * balas/teruskan: panel ini hanya satu sisi percakapan dengan
                     * pelanggan, jadi yang offered adalah reactions, salin,
                     * tandai, dan hapus permanen. Gelembung untuk panel User
                     * construida terpisah di blade-nya masing-masing, tapi aturan
                     * perakitannya (ikon jadi HTML, argumen jadi JSON) sama
                     * thanks MessageBubble.
                     */
                    $bubbleActions = [];

                    if ($hasText) {
                        $bubbleActions[] = MessageBubble::copyAction(
                            'copy',
                            __('Salin'),
                            '/images/icons/clipboard.svg',
                            $displayMessage,
                        );
                    }

                    $bubbleActions[] = MessageBubble::wireAction(
                        'star',
                        $isStarred ? __('Batal Tandai') : __('Tandai'),
                        $isStarred ? '/images/icons/star.svg' : '/images/icons/star.svg',
                        'toggleStar',
                        [(int) $message->id],
                    );

                    $bubbleActions[] = MessageBubble::wireAction(
                        'delete_everyone',
                        __('Hapus Pesan'),
                        '/images/icons/trash.svg',
                        'deleteMessage',
                        [(int) $message->id],
                        'danger',
                    );

                    $bubblePayload = MessageBubble::payload((int) $message->id, $bubbleActions);
                @endphp

                {{-- Message Row --}}
                {{--
                    data-msg-bubble menandai baris ini sebagai jangkar gelembung.
                    data-msg-payload dibaca Alpine lewat JSON.parse, jadi
                    atributnya dibungkus tanda kutip tunggal: isi JSON memakai tanda
                    kutip ganda, dan @json sudah mengganti kutip tunggal di
                    dalamnya jadi \u0027 sehingga aman di dalam atribut ini.
                --}}
                <div @class(['flex mb-3', 'justify-end' => $isMine, 'justify-start' => !$isMine])
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
                                                ? route('filament.admin.resources.orders.view', ['record' => $payViewOrderId])
                                                : route('filament.admin.resources.orders.index');
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
                                            ? route('filament.admin.resources.orders.view', ['record' => $viewOrderId])
                                            : route('filament.admin.resources.orders.index');
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
                                            @php
                                                $inboxId = $selectedConversation?->id ?? '';
                                                $adminViewUrl = $viewOrderId
                                                    ? route('filament.admin.resources.orders.view', ['record' => $viewOrderId]) . '?from=messages&inbox=' . $inboxId
                                                    : route('filament.admin.resources.orders.index');
                                            @endphp
                                            <a href="{{ $adminViewUrl }}"
                                               wire:navigate
                                               class="flex-1 py-3 text-sm font-semibold text-center transition-colors {{ $isCancelledCard ? 'rounded-b-xl' : 'rounded-bl-xl border-r border-gray-600' }}"
                                               style="background-color:#334155;color:#f1f5f9;">
                                                {{ __('View Details') }}
                                            </a>
                                            @if(!$isCancelledCard)
                                                <button wire:click="mountAction('changeOrder', {{ json_encode(['orderId' => $meta['order_id'] ?? null]) }})"
                                                    class="flex-1 py-3 text-sm font-semibold transition-colors rounded-br-xl"
                                                    style="background-color:#facc15;color:#111827;">
                                                    {{ __('Change Order') }}
                                                </button>
                                            @endif
                                    </div>
                                @endif
                            </div>
                        @endif

                        {{-- Text / Media bubble --}}
                        @if ($hasText || $hasMedia)
                            @php
                                // Bubble color follows panel primary color
                                // admin = Indigo (#4338ca), user = Yellow (#eab308 with dark text)
                                $bubbleStyle = $isMine ? 'background-color:#4338ca;color:#fff;' : '';
                            @endphp
                            <div @class([
                                'px-3 py-2 rounded-2xl',
                                'rounded-br-sm' => $isMine,
                                'rounded-bl-sm text-gray-900 dark:text-white bg-gray-100 dark:bg-gray-600' => !$isMine,
                            ]) style="{{ $bubbleStyle }}">
                                @if ($hasText)
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

                        {{-- Reactions --}}
                        {{--
                            Pill ini adalah tempat pengguna melihat reaksi yang
                            sudah diketuk lewat gelembung. Tanpa baris ini, admin
                            menekan emoji dan tidak ada yang berubah di layar, dan
                            reaksi dari panel User terlihat hilang begitu panel
                            Admin selesai polling.
                        --}}
                        @if ($groupedReactions->isNotEmpty())
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
                        <div @class(['flex items-center gap-1 mt-0.5 px-1', 'justify-end' => $isMine, 'justify-start' => !$isMine])>
                            @if ($isStarred)
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
                    $nextMessage = $conversationMessages[$index + 1] ?? null;
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
            @include('User.components.cbir-browse-modal.cbir-browse-modal', [
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
            @include('Shared.components.attachment-menu.attachment-menu')

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




    </script>
@endscript
