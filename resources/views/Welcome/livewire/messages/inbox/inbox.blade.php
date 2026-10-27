@php
    $messagesPage = \App\Filament\Welcome\Pages\MessagesPage\MessagesPage::class;
    $authId = (int) $this->chatUserId();
    $conversations = $this->conversations;
    $filtered = $this->filteredConversations;
    $needsName = $this->isChatGuest() && $this->needsName();
@endphp

{{--
    Latar panel INBOX sengaja transparan, bukan `bg-white dark:bg-gray-900
    rounded-xl` seperti sebelumnya.

    Alasannya: halaman Messages sudah dibebas dari kartu halaman Filament
    (`<x-filament-panels::page>` dibuang), jadi yang tersisa untuk "kartu" hanyalah
    elemen ini -- dan bentuknya persis yang sedang dihapus: satu blok gelap
    besar dengan sudut membulat mengapung di tengah panel. Latar panelnya sendiri
    sudah menyediakan warna yang benar; lapisan kedua hanya menambah garis tepi
    yang tidak diminta siapa pun.

    Yang TIDAK ikut disentuh: kartu-kartu di DALAM (kartu	status kuning, kartu
    kategori, baris percakapan). Itu isi halaman, bukan wadahnya.
--}}
<div wire:poll.visible.{{ $pollInterval }}="loadConversations"
    class="w-full h-full overflow-y-auto">
    <div class="mx-auto flex w-full max-w-2xl flex-col gap-4 p-4 sm:p-6">

        {{-- Status card --}}
        <div class="flex items-center gap-4 rounded-2xl p-4 text-white shadow-lg"
            style="background: linear-gradient(135deg, rgb(var(--primary-500)), rgb(var(--primary-700)));">
            <span class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-white/20">
                <x-filament::icon icon="/images/icons/lifebuoy.svg" class="h-7 w-7 text-white" />
            </span>
            <div class="min-w-0">
                <p class="font-semibold leading-tight">{{ __('Selamat Datang di Customer Service!') }}</p>
                <div class="mt-0.5 flex items-center gap-1.5">
                    <span class="h-2 w-2 rounded-full" style="background-color: #7CFC9A;"></span>
                    <span class="text-sm text-white/90">{{ __('Tim kami sedang online') }}</span>
                </div>
            </div>
        </div>

        {{-- Search membuka modal supaya tidak memakan ruang inbox. --}}
        <button type="button" class="flex w-full items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2 text-start text-sm text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-400"
            x-on:click="$dispatch('open-modal', { id: 'welcome-conversation-search' })">
            <x-filament::icon icon="/images/icons/search.svg" class="h-5 w-5" />
            <span>{{ __('Cari percakapan...') }}</span>
        </button>
        <livewire:fm-welcome-search />

        {{-- Name prompt for guests --}}
        @if ($needsName)
            <x-filament::section
                :heading="__('Sebelum memulai, silakan beri tahu kami nama Anda')"
                :description="__('Nama ini akan ditampilkan kepada tim CS agar mereka tahu bagaimana memanggil Anda.')"
                icon="/images/icons/user_circle.svg"
                icon-color="warning"
            >
                <form
                    wire:submit.prevent="setGuestName"
                    class="flex gap-2"
                    action="{{ $messagesPage::getUrl(panel: 'welcome') }}">
                    <x-filament::input type="text" wire:model.live="guestName"
                        placeholder="{{ __('Nama Anda') }}"
                        class="flex-1 min-w-0"
                        required />
                    <x-filament::button type="submit" color="warning" class="shrink-0">
                        {{ __('Simpan') }}
                    </x-filament::button>
                </form>
            </x-filament::section>
        @endif

        {{-- Your conversations --}}
        @if ($conversations->isNotEmpty())
            <div class="flex items-center justify-between">
                <p class="font-semibold text-gray-950 dark:text-white">{{ __('Percakapan Anda') }}</p>
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $conversations->count() }}</span>
            </div>

            @if ($filtered->isEmpty())
                <div
                    class="rounded-xl border border-gray-200 p-4 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                    {{ __('Tidak ada percakapan yang cocok') }}
                </div>
            @else
                <div class="flex flex-col gap-2">
                    @foreach ($filtered as $conversation)
                        @php
                            $latestMessage = $conversation->messages->sortByDesc('id')->first();
                            $unread = $conversation->messages
                                ->where('user_id', '!=', $authId)
                                ->filter(fn ($message) => ! in_array($authId, array_map('intval', $message->read_by ?? [])))
                                ->count();
                            $preview = trim((string) ($latestMessage->message ?? ''));
                        @endphp

                        <div wire:key="conversation-{{ $conversation->id }}" class="relative">
                            <a wire:navigate
                                href="{{ $messagesPage::getUrl(panel: 'welcome', tenant: filament()->getTenant()) . '/' . $conversation->id }}"
                                @class([
                                    'flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-3 pr-10 transition dark:border-white/10 dark:bg-white/5',
                                    'hover:bg-gray-50 dark:hover:bg-white/10',
                                ])>
                                <span class="relative flex-shrink-0">
                                    <x-filament::avatar src="{{ $conversation->primary_avatar }}"
                                        alt="{{ urlencode($conversation->inbox_title) }}" size="lg" />
                                    @if ($unread > 0)
                                        <span
                                            class="absolute -right-1 -top-1 flex h-[18px] min-w-[18px] items-center justify-center rounded-full px-1 text-[10px] font-semibold text-white"
                                            style="background-color: rgb(var(--primary-500));">
                                            {{ $unread > 99 ? '99+' : $unread }}
                                        </span>
                                    @endif
                                </span>

                                <span class="min-w-0 flex-1">
                                    <span @class([
                                        'block truncate text-sm',
                                        'font-semibold text-gray-950 dark:text-white' => $unread > 0,
                                        'font-semibold text-gray-900 dark:text-gray-100' => $unread === 0,
                                    ])>{{ $conversation->inbox_title }}</span>
                                    <span @class([
                                        'block truncate text-sm',
                                        'font-medium text-gray-950 dark:text-gray-200' => $unread > 0,
                                        'text-gray-500 dark:text-gray-400' => $unread === 0,
                                    ])>{{ $preview !== '' ? $preview : __('Tidak ada pesan') }}</span>
                                </span>

                                <span class="whitespace-nowrap text-[11px] text-gray-400">
                                    {{ $latestMessage?->created_at?->diffForHumans(short: true) }}
                                </span>
                            </a>

                            <div class="absolute right-2 top-2">
                                <x-filament::icon-button icon="/images/icons/trash.svg" color="danger" size="xs"
                                    tooltip="{{ __('Delete') }}"
                                    wire:click.stop="deleteConversation({{ $conversation->id }})"
                                    wire:confirm="{{ __('Are you sure you want to delete this conversation?') }}" />
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        @else
            <p class="text-sm text-gray-600 dark:text-gray-400">
                {{ __('Pilih topik di bawah atau langsung kirim pesan. Tim kami akan membantu Anda sesegera mungkin.') }}
            </p>
        @endif

        {{-- CS quick actions --}}
        <div class="flex flex-col gap-2">
            @foreach ($this->csActions() as $action)
                <button type="button" wire:key="cs-{{ $action['key'] }}"
                    wire:click="startChatWithCategory('{{ $action['key'] }}')" wire:loading.attr="disabled"
                    class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-3 text-left transition hover:bg-gray-50 disabled:opacity-50 dark:border-white/10 dark:bg-white/5 dark:hover:bg-white/10">
                    <span
                        class="cs-action-icon flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl {{ $action['color'] }}">
                        <img src="{{ $action['icon'] }}" alt="" aria-hidden="true"
                            class="block h-5 w-5 shrink-0 object-contain" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-semibold text-gray-950 dark:text-white">{{ $action['title'] }}</span>
                        <span
                            class="block truncate text-sm text-gray-500 dark:text-gray-400">{{ $action['subtitle'] }}</span>
                    </span>
                    <x-filament::icon icon="/images/icons/chevron_right.svg" class="h-5 w-5 text-gray-400" />
                </button>
            @endforeach
        </div>

        {{-- New chat --}}
        <x-filament::button wire:click="startNewChat" class="w-full" size="lg">
            {{ __('Chat Baru') }}
        </x-filament::button>
    </div>
</div>
