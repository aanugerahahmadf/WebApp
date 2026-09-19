@php
    use App\Enums\Messages\MediaCollectionType\MediaCollectionType;
@endphp
@props(['selectedConversation'])
<!-- Right Section (Chat Box) -->
<div class="w-full h-full bg-white rounded-xl dark:divide-white/10 dark:bg-gray-900 overflow-hidden flex flex-col min-h-0">
    @if ($selectedConversation)
        <!-- Chat Header : Start -->
        <div class="grid grid-cols-[var(--cols-default)] lg:grid-cols-[var(--cols-lg)] p-6"
            style="--cols-default: repeat(1, minmax(0, 1fr)); --cols-lg: repeat(1, minmax(0, 1fr));">
            <div style="--col-span-default: 1 / -1;" class="col-[var(--col-span-default)]">
                <div class="flex gap-4 items-center">
                    <x-filament::icon-button
                        icon="heroicon-o-chevron-left"
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
                    @endphp

                    <x-filament::avatar src="{{ $avatar }}" alt="{{ $alt }}" size="lg" />

                    <div class="flex-1 overflow-hidden">
                        <div class="flex justify-between items-center gap-2">
                            <p class="text-base font-bold truncate text-gray-900 dark:text-white">{{ $selectedConversation->inbox_title }}</p>
                        </div>

                        @if ($selectedConversation->title)
                            <p class="text-sm truncate text-gray-600 dark:text-gray-400">
                                {{ $selectedConversation->other_users->pluck('name')->implode(', ') }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <!-- Chat Header : End -->
        <!-- Chat Box : Start -->
        <div wire:poll.visible.{{ $pollInterval }}="pollMessages()" id="chatContainer"
            class="flex flex-col-reverse flex-1 min-h-0 p-2 sm:p-5 overflow-y-auto">
            @foreach ($conversationMessages as $index => $message)
                @php
                    $isMine = $message->user_id === auth()->id();
                    $hasMeta = $message->meta && isset($message->meta['type']);
                    $hasText = !empty($message->message);
                    $hasMedia = $message->getMedia(MediaCollectionType::FILAMENT_MESSAGES->value)?->count() > 0;

                    $meta = $message->meta ?? [];
                    // Normalize legacy messages that stored "\\n" as text, then
                    // let nl2br() below render the resulting line breaks safely.
                    $displayMessage = \App\Support\Chat\BotReplyLocalizer::display($message);
                    $displayMessage = str_replace(['\\r\\n', '\\n', '\\r'], ["\r\n", "\n", "\r"], $displayMessage);
                    if ($hasText && is_array($meta) && isset($meta['type'], $meta['name']) && !isset($meta['is_order'])) {
                        $displayMessage = __('Saya menanyakan tentang :itemType ini: :name', [
                            'itemType' => __($meta['type'] === 'product' ? 'Produk' : 'Paket'),
                            'name' => $meta['name'],
                        ]);
                    }
                    // For order messages, use the original message text from DB (set by ChatService)

                    $createdAt = \Carbon\Carbon::parse($message->created_at)->setTimezone(config('messages.timezone', 'app.timezone'));
                    $date = $createdAt->isToday() ? $createdAt->format('H:i') : $createdAt->format('d/m/y H:i');
                    $isRead = !empty($message->read_by) && count(array_filter($message->read_by, fn($id) => $id !== auth()->id())) > 0;
                @endphp

                {{-- Message Row --}}
                <div @class(['flex mb-3', 'justify-end' => $isMine, 'justify-start' => !$isMine])
                     wire:key="{{ $message->id }}">

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
                        @if ($hasMeta)
                            @php
                                $itemImage = $meta['image'] ?? null;
                                if ($itemImage) {
                                    $itemImage = \App\Providers\NativeServiceProvider\NativeServiceProvider::normalizeUrl($itemImage);
                                }
                                if (!$itemImage || str_contains($itemImage, 'placeholder') || str_contains((string) $itemImage, 'placeholders')) {
                                    $modelClass = $meta['type'] === 'product' ? \App\Models\Product\Product::class : \App\Models\Package\Package::class;
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
                                            <p class="text-[9px] font-black tracking-tight leading-none mb-0.5" style="color:#fca5a5;">
                                                ❌ {{ __('PESANAN DIBATALKAN') }}
                                            </p>
                                        @elseif($isOrderCard || $isPaymentUpdate)
                                            <p class="text-[9px] font-black tracking-tight leading-none mb-0.5" style="color:#facc15;">
                                                {{ __('Pesanan') }} #{{ $meta['order_number'] ?? '-' }}
                                            </p>
                                        @endif
                                        <p class="text-sm font-bold truncate leading-tight" style="{{ $nameColor }}">{{ $meta['name'] }}</p>
                                        <p class="text-xs font-black leading-none mt-0.5" style="{{ $nameColor }}">
                                            Rp {{ number_format($meta['price'], 0, ',', '.') }}
                                        </p>
                                        @if($isCancellation)
                                            <span class="inline-block mt-1 px-1.5 py-0.5 rounded text-[9px] font-bold leading-none"
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
                                            <span class="inline-block mt-1 px-1.5 py-0.5 rounded text-[9px] font-bold leading-none"
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
                                           class="flex-shrink-0 px-3 py-1.5 rounded-lg text-xs font-bold transition-colors"
                                           style="background-color:#1c1917;color:#facc15;">
                                            {{ __('Lihat Detail') }}
                                        </a>
                                    @elseif(!$isOrderCard && !empty($meta['url']))
                                        <a href="{{ $meta['url'] }}" wire:navigate
                                           class="flex-shrink-0 px-3 py-1.5 rounded-lg text-xs font-bold transition-colors"
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
                                            <span class="inline-block px-2 py-0.5 rounded text-[9px] font-bold"
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
                                               class="flex-1 py-3 text-sm font-bold text-center transition-colors {{ $isCancelledCard ? 'rounded-b-xl' : 'rounded-bl-xl border-r border-gray-600' }}"
                                               style="background-color:#334155;color:#f1f5f9;">
                                                {{ __('View Details') }}
                                            </a>
                                            @if(!$isCancelledCard)
                                                <button wire:click="mountAction('changeOrder', {{ json_encode(['orderId' => $meta['order_id'] ?? null]) }})"
                                                    class="flex-1 py-3 text-sm font-bold transition-colors rounded-br-xl"
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
                                    <p class="text-sm leading-relaxed">{!! nl2br(e($displayMessage)) !!}</p>
                                @endif

                                @if ($hasMedia)
                                    @foreach ($message->getMedia(MediaCollectionType::FILAMENT_MESSAGES->value) as $media)
                                        @php $isImage = $this->validateImage($media->file_name); @endphp
                                        @if($isImage)
                                            <div class="mt-1 relative group">
                                                <img src="{{ $media->getUrl() }}"
                                                     class="rounded-lg max-w-full h-auto cursor-pointer shadow-sm"
                                                     wire:click="downloadAttachment({{ $media->id }})" />
                                                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center rounded-lg pointer-events-none">
                                                    <x-filament::icon icon="heroicon-o-arrow-down-tray" class="w-6 h-6 text-white" />
                                                </div>
                                            </div>
                                        @else
                                            <div wire:click="downloadAttachment({{ $media->id }})"
                                                @class([
                                                    'flex items-center gap-2 p-2 mt-1 rounded-lg cursor-pointer',
                                                    'bg-gray-200 dark:bg-gray-500' => !$isMine,
                                                    'bg-primary-500 dark:bg-primary-400' => $isMine,
                                                ])>
                                                @php
                                                    $icon = 'heroicon-o-document';
                                                    if ($this->validateDocument($media->file_name)) $icon = 'heroicon-o-paper-clip';
                                                    if ($this->validateVideo($media->file_name)) $icon = 'heroicon-o-video-camera';
                                                    if ($this->validateAudio($media->file_name)) $icon = 'heroicon-o-speaker-wave';
                                                @endphp
                                                <x-filament::icon icon="{{ $icon }}" class="w-4 h-4" />
                                                <p class="text-sm truncate">{{ $media->file_name }}</p>
                                            </div>
                                        @endif
                                    @endforeach
                                @endif
                            </div>
                        @endif

                        {{-- Timestamp + read status --}}
                        <div @class(['flex items-center gap-1 mt-0.5 px-1', 'justify-end' => $isMine, 'justify-start' => !$isMine])>
                            <span class="text-[10px] text-gray-400 dark:text-gray-500">{{ $date }}</span>
                            @if ($isMine)
                                @if ($isRead)
                                    <svg class="w-3.5 h-3.5 text-blue-400" viewBox="0 0 24 24" fill="none">
                                        <path d="M1.5 12.5l5 5L18 5" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M6 12.5l5 5L22.5 5" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                    <span class="text-[9px] text-blue-400">{{ __('Dibaca') }}</span>
                                @else
                                    <svg class="w-3.5 h-3.5 text-gray-400" viewBox="0 0 24 24" fill="none">
                                        <path d="M4 12.5l5 5L20 6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
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
                'isNative' => \App\Providers\NativeServiceProvider\NativeServiceProvider::isAnyMobile(),
            ])
            @include('User.components.messages-camera-modal.messages-camera-modal')

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
            <form wire:submit="sendMessage()" class="flex items-end justify-between w-full gap-4">
                <div class="w-full max-h-96 overflow-y-auto p-1">
                    {{ $this->form }}
                </div>
                <div class="p-1">
                    <x-filament::button 
                        wire:click="sendMessage()" 
                        icon="heroicon-o-paper-airplane"
                        wire:loading.attr="disabled"
                        class="native-send-btn">
                    </x-filament::button>
                </div>
            </form>
            <x-filament-actions::modals />
        </div>
        <!-- Chat Input : End -->

        <!-- Camera Modal : Start -->
        <div id="camera-modal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm" style="display:none;">
            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl overflow-hidden w-full max-w-md mx-4">
                <!-- Modal Header -->
                <div class="flex items-center justify-between px-5 py-4 border-b dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-800 dark:text-white flex items-center gap-2">
                        <x-filament::icon icon="heroicon-o-camera" class="w-5 h-5 text-primary-500" />
                        {{ __('Take a Photo') }}
                    </h3>
                    <button id="close-camera-btn" type="button"
                        class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                        <x-filament::icon icon="heroicon-o-x-mark" class="w-5 h-5" />
                    </button>
                </div>
                <!-- Video / Preview -->
                <div class="relative bg-black">
                    <video id="camera-video" autoplay playsinline
                        class="w-full" style="max-height: 320px; object-fit: cover;"></video>
                    <canvas id="camera-canvas" class="hidden w-full" style="max-height: 320px; object-fit: cover;"></canvas>
                    <!-- Switch camera overlay button -->
                    <button id="switch-camera-btn" type="button"
                        class="absolute top-3 right-3 bg-black/50 hover:bg-black/70 text-white p-2 rounded-full transition">
                        <x-filament::icon icon="heroicon-o-arrow-path" class="w-5 h-5" />
                    </button>
                </div>
                <!-- Controls -->
                <div id="camera-controls-capture" class="flex items-center justify-center gap-4 p-5">
                    <button id="capture-btn" type="button"
                        class="w-16 h-16 bg-primary-600 hover:bg-primary-700 text-white rounded-full flex items-center justify-center shadow-lg transition-transform hover:scale-105">
                        <x-filament::icon icon="heroicon-o-camera" class="w-7 h-7" />
                    </button>
                </div>
                <div id="camera-controls-preview" class="flex items-center justify-between gap-3 px-5 pb-5" style="display:none;">
                    <button id="retake-btn" type="button"
                        class="flex-1 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 text-sm font-medium transition">
                        {{ __('Retake') }}
                    </button>
                    <button id="send-photo-btn" type="button"
                        class="flex-1 py-2.5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium transition">
                        {{ __('Send Photo') }}
                    </button>
                </div>
            </div>
        </div>
        <!-- Camera Modal : End -->

    @else
        <div class="flex flex-col items-center justify-center h-full p-3">
            <div class="p-3 mb-4 bg-gray-100 rounded-full dark:bg-gray-500/20">
                <x-filament::icon icon="heroicon-o-x-mark" class="w-6 h-6 text-gray-500 dark:text-gray-400" />
            </div>
            <p class="text-base text-center text-gray-600 dark:text-gray-400">
                {{ __('No selected conversation') }}
            </p>
        </div>
    @endif
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




        let cameraStream = null;
        let facingMode = 'user'; // 'user' = front, 'environment' = back

        async function startCamera() {
            if (cameraStream) {
                cameraStream.getTracks().forEach(t => t.stop());
            }
            try {
                cameraStream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: facingMode },
                    audio: false
                });
                const video = document.getElementById('camera-video');
                video.srcObject = cameraStream;

                // Reset to live view
                video.classList.remove('hidden');
                document.getElementById('camera-canvas').classList.add('hidden');
                document.getElementById('camera-controls-capture').classList.remove('hidden');
                document.getElementById('camera-controls-preview').style.display = 'none';
            } catch (err) {
                alert('{{ __('Cannot access camera. Please allow camera permission.') }}');
                closeCameraModal();
            }
        }

        function closeCameraModal() {
            if (cameraStream) {
                cameraStream.getTracks().forEach(t => t.stop());
                cameraStream = null;
            }
            document.getElementById('camera-modal').style.display = 'none';
        }


        window.addEventListener('open-camera', () => {
            document.getElementById('camera-modal').style.display = 'flex';
            facingMode = 'environment';
            startCamera();
        });

        document.getElementById('close-camera-btn').addEventListener('click', closeCameraModal);

        document.getElementById('switch-camera-btn').addEventListener('click', () => {
            facingMode = facingMode === 'user' ? 'environment' : 'user';
            startCamera();
        });

        document.getElementById('capture-btn').addEventListener('click', () => {
            const video = document.getElementById('camera-video');
            const canvas = document.getElementById('camera-canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);

            video.classList.add('hidden');
            canvas.classList.remove('hidden');
            document.getElementById('camera-controls-capture').classList.add('hidden');
            document.getElementById('camera-controls-preview').style.display = 'flex';
        });

        document.getElementById('retake-btn').addEventListener('click', () => {
            startCamera();
        });

        document.getElementById('send-photo-btn').addEventListener('click', async () => {
            const canvas = document.getElementById('camera-canvas');
            canvas.toBlob(async (blob) => {
                const fileName = 'camera_' + Date.now() + '.jpg';
                const file = new File([blob], fileName, { type: 'image/jpeg' });

                // Use FilePond or native input — inject into a hidden file input
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);

                // Find the Livewire file input for attachments
                const fileInputs = document.querySelectorAll('input[type="file"]');
                if (fileInputs.length > 0) {
                    const fileInput = fileInputs[0];
                    fileInput.files = dataTransfer.files;
                    fileInput.dispatchEvent(new Event('change', { bubbles: true }));
                    closeCameraModal();
                } else {
                    // Fallback: download the image
                    const a = document.createElement('a');
                    a.href = canvas.toDataURL('image/jpeg');
                    a.download = fileName;
                    a.click();
                    closeCameraModal();
                }
            }, 'image/jpeg', 0.92);
        });
    </script>
@endscript
