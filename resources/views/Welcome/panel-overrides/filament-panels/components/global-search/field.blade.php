@php
    $debounce = filament()->getGlobalSearchDebounce();
    $keyBindings = filament()->getGlobalSearchKeyBindings();
    $suffix = filament()->getGlobalSearchFieldSuffix();
@endphp

<div
        x-id="['input']"
        {{ $attributes->class(['fi-global-search-field']) }}
    >
        <label x-bind:for="$id('input')" class="sr-only">
            {{ __('filament-panels::global-search.field.label') }}
        </label>

        {{-- Global Search wrapper: Blur 95% + color tint 20px --}}
        <div class="fi-input-wrp flex items-center rounded-lg"
            style="
                background-color: var(--fi-glass-bg);
                background-image: linear-gradient(var(--fi-glass-tint), var(--fi-glass-tint));
                -webkit-backdrop-filter: var(--fi-glass-blur);
                backdrop-filter: var(--fi-glass-blur);
                border: 1px solid var(--fi-glass-ring);
                box-shadow: inset 0 1px 0 rgb(255 255 255 / 0.35), 0 1px 2px rgb(0 0 0 / 0.05), 0 12px 28px -16px rgb(0 0 0 / 0.35);
            ">
            {{-- Prefix icon --}}
            <div class="flex items-center ps-3 text-gray-400 dark:text-gray-500 shrink-0">
                <x-filament::icon
                    alias="panels::global-search.field"
                    icon="heroicon-m-magnifying-glass"
                    class="h-5 w-5"
                />
            </div>

            {{-- Input --}}
            <x-filament::input
                autocomplete="off"
                inline-prefix
                maxlength="1000"
                :placeholder="__('filament-panels::global-search.field.placeholder')"
                type="search"
                wire:key="global-search.field.input"
                x-bind:id="$id('input')"
                x-on:keydown.down.prevent.stop="$dispatch('focus-first-global-search-result')"
                x-data="{}"
                class="flex-1 min-w-0 border-0 bg-transparent py-2 ps-2 pe-2 text-sm text-gray-950 placeholder:text-gray-400 focus:ring-0 dark:text-white dark:placeholder:text-gray-500"
                :attributes="
                    \Filament\Support\prepare_inherited_attributes(
                        new \Illuminate\View\ComponentAttributeBag([
                            'wire:model.live.debounce.' . $debounce => 'search',
                            'x-mousetrap.global.' . collect($keyBindings)->map(fn (string $keyBinding): string => str_replace('+', '-', $keyBinding))->implode('.') => $keyBindings ? 'document.getElementById($id(\'input\')).focus()' : null,
                        ])
                    )
                "
            />

            {{-- CBIR camera button — lives in the Welcome namespace, mounted from
                 both topbars (user + welcome). One class, two entry points. --}}
            @livewire(\App\Livewire\Welcome\CbirCameraButton\CbirCameraButton::class)

            {{-- Original suffix if any --}}
            @if($suffix)
                <div class="pe-3 text-sm text-gray-500 dark:text-gray-400 shrink-0">{{ $suffix }}</div>
            @endif
        </div>
    </div>