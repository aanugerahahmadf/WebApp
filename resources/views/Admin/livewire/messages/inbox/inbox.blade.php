@php
    $messagesPage = \App\Filament\Admin\Pages\MessagesPage\MessagesPage::class;
    use App\Enums\Messages\MediaCollectionType\MediaCollectionType;
@endphp

@props(['selectedConversation'])
<div wire:poll.visible.{{ $pollInterval }}="loadConversations"
    style="height: 100%; display: flex; flex-direction: column;"
    class="w-full h-full bg-white rounded-xl dark:divide-white/10 dark:bg-gray-900 p-6">
    <div class="flex flex-col gap-4 mb-6">
        <div class="flex items-center justify-between">
            <div class="flex gap-4 items-center">
                <p class="text-xl font-bold text-gray-950 dark:text-white">{{__('Inbox')}}</p>
                @if ($this->unreadCount() > 0)
                    <x-filament::badge color="primary" size="sm">
                        {{ $this->unreadCount() }}
                    </x-filament::badge>
                @endif
            </div>
            <div class="flex-none">
                {{ $this->createConversation }}
            </div>
        </div>
        <div class="w-full">
            <x-filament::input.wrapper suffix-icon="heroicon-o-magnifying-glass" class="w-full">
                <x-filament::input type="text" placeholder="{{__('Search messages...')}}"
                    x-on:click="$dispatch('open-modal', { id: 'search-conversation' })"
                    class="w-full" />
            </x-filament::input.wrapper>
        </div>
    </div>

    <livewire:fm-admin-search />

    <!-- Inbox : Start -->
    <div @style([
        'height: calc(100% - 120px)' => $this->conversations->count() > 0,
        'margin-top: 1.5rem;'
    ]) @class([
    'flex-1 overflow-y-auto' => $this->conversations->count() > 0,
])>
        @if ($this->conversations->count() > 0)
            <div class="grid w-full">
                @foreach ($this->conversations as $conversation)
                    @php
                        $latestMessage = $conversation->latestMessage();
                        $isRead = $latestMessage ? in_array(auth()->id(), $latestMessage->read_by ?? []) : true;
                    @endphp
                    <a wire:key="{{ $conversation->id }}" wire:navigate
                        href="{{ $messagesPage::getUrl(panel: 'admin', tenant: filament()->getTenant()) . '/' . $conversation->id }}" @class([
                            'p-2 rounded-xl w-full mb-2',
                            'hover:bg-gray-100 dark:hover:bg-white/10' => $conversation->id != $selectedConversation?->id,
                            'bg-gray-100 dark:bg-white/10 dark:text-white' => $conversation->id == $selectedConversation?->id,
                            'bg-gray-100 dark:bg-white/10' => !$isRead
                        ])>
                        <div class="grid grid-cols-[var(--cols-default)] lg:grid-cols-[var(--cols-lg)]"
                            style="--cols-default: repeat(1, minmax(0, 1fr)); --cols-lg: repeat(6, minmax(0, 1fr));">
                            <div style="--col-span-default: span 5 / span 5;" class="col-[var(--col-span-default)]">
                                <div class="flex gap-2">
                                    @php
                                        $avatar = $conversation->primary_avatar;
                                        $alt = urlencode($conversation->inbox_title);
                                    @endphp

                                    <x-filament::avatar src="{{ $avatar }}" alt="{{ $alt }}" size="lg" />

                                    <div class="overflow-hidden">
                                        <p @class([
                                            'text-sm font-semibold truncate',
                                            'text-gray-950 dark:text-white font-bold' => !$isRead
                                        ])>{{ $conversation->inbox_title }}</p>

                                        @if ($latestMessage)
                                            <p @class([
                                                'text-sm truncate dark:text-gray-400',
                                                'text-gray-600' => $isRead,
                                                'text-gray-950 dark:text-gray-200 font-medium' => !$isRead
                                            ])>
                                                <span class="font-bold">
                                                    {{ $latestMessage->user_id == auth()->id() ? __('You:') : ($latestMessage->sender->name ?? __('User')) . ':' }}
                                                </span>
                                                @php
                                                    $media = $latestMessage->getMedia(MediaCollectionType::FILAMENT_MESSAGES->value);
                                                    $mediaCount = $media ? count($media) : 0;
                                                @endphp
                                                @if ($mediaCount > 0)
                                                    {{ $mediaCount > 1 ? __('Attachments') : __('Attachment') }}
                                                @else
                                                    {{ $latestMessage->message }}
                                                @endif
                                            </p>
                                        @else
                                            <p class="text-xs text-gray-400">{{ __('No messages') }}</p>
                                        @endif

                                    </div>
                                </div>
                            </div>
                            <div style="--col-span-default: span 1 / span 1;" class="col-[var(--col-span-default)]">
                                <div class="flex flex-col items-end gap-2 h-full justify-between pb-1">
                                    <p @class([
                                        'text-sm font-light whitespace-nowrap',
                                        'text-gray-600 dark:text-gray-500' => $isRead,
                                        'font-semibold text-gray-950 dark:text-gray-300' => !$isRead
                                    ])>
                                        {{ \Carbon\Carbon::parse($conversation->updated_at)->setTimezone(config('messages.timezone', 'app.timezone'))->shortAbsoluteDiffForHumans() }}
                                    </p>

                                    <x-filament::icon-button icon="heroicon-o-trash" color="danger" size="xs"
                                        tooltip="{{ __('Delete') }}"
                                        wire:click.stop="deleteConversation({{ $conversation->id }})"
                                        wire:confirm="{{ __('Are you sure you want to delete this conversation?') }}" />
                                </div>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        @else
            <div class="flex flex-col items-center justify-center h-full p-3">
                <div class="p-3 mb-4 bg-gray-100 rounded-full dark:bg-gray-500/20">
                    <x-filament::icon icon="heroicon-o-x-mark" class="w-6 h-6 text-gray-500 dark:text-gray-400" />
                </div>
                <p class="text-base text-center text-gray-600 dark:text-gray-400">
                    {{__('No conversations yet')}}
                </p>
            </div>
        @endif
    </div>
    <!-- Inbox : End -->
    <x-filament-actions::modals />
</div>