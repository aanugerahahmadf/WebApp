@props([
    'results',
])

<div
    x-data="{
        isOpen: false,

        open: function (event) {
            this.isOpen = true
        },

        close: function (event) {
            this.isOpen = false
        },
    }"
    x-init="$nextTick(() => open())"
    x-on:click.away="close()"
    x-on:keydown.escape.window="close()"
    x-on:keydown.up.prevent="$focus.wrap().previous()"
    x-on:keydown.down.prevent="$focus.wrap().next()"
    x-on:open-global-search-results.window="$nextTick(() => open())"
    x-show="isOpen"
    x-transition:enter-start="opacity-0"
    x-transition:leave-end="opacity-0"
    {{
        $attributes->class([
            'fi-global-search-results-ctn absolute inset-x-4 z-10 mt-2 max-h-96 overflow-auto rounded-lg bg-white shadow-lg ring-1 ring-gray-950/5 transition dark:bg-gray-900 dark:ring-white/10 sm:inset-x-auto sm:end-0 sm:w-screen sm:max-w-sm',
            '[transform:translateZ(0)]',
        ])
    }}
>
    {{-- Loading state — tampil saat Livewire sedang fetch --}}
    <div
        wire:loading.flex
        wire:target="search"
        class="items-center justify-center gap-2 px-4 py-6 text-sm text-gray-500 dark:text-gray-400"
    >
        <svg class="h-4 w-4 animate-spin text-primary-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
        <span>{{ __('Mencari...') }}</span>
    </div>

    {{-- Results — hanya tampil saat tidak loading --}}
    <div wire:loading.remove wire:target="search">
        @if ($results->getCategories()->isEmpty())
            <x-filament-panels::global-search.no-results-message />
        @else
            <ul class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($results->getCategories() as $group => $groupedResults)
                    <x-filament-panels::global-search.result-group
                        :label="$group"
                        :results="$groupedResults"
                    />
                @endforeach
            </ul>
        @endif
    </div>
</div>
