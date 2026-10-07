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

    {{-- Global Search: transparan dan tanpa shadow. Field ini ada DI DALAM
         topbar yang sudah ber-glass, jadi efek blur 20px + gold 35%
         milik topnav terlihat langsung lewat field. Menambah
         backdrop-filter, warna latar, atau bayangan sendiri hanya
         membuat menumpuk dan teks jadi kabur. --}}
    <div class="fi-input-wrp flex items-center rounded-lg"
        style="
            background-color: transparent;
            border: 1px solid var(--fi-glass-ring);
            box-shadow: none;
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

        {{-- CBIR camera button -- lives in the Welcome namespace, mounted from
             both topbars (user + welcome). One class, two entry points. --}}
        @livewire(\App\Livewire\Welcome\CbirCameraButton\CbirCameraButton::class)

        {{-- Original suffix if any --}}
        @if($suffix)
            <div class="pe-3 text-sm text-gray-500 dark:text-gray-400 shrink-0">{{ $suffix }}</div>
        @endif
    </div>
</div>