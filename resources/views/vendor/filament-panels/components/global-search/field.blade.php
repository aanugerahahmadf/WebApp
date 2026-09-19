@php
    $debounce = filament()->getGlobalSearchDebounce();
    $keyBindings = filament()->getGlobalSearchKeyBindings();
    $suffix = filament()->getGlobalSearchFieldSuffix();
    $isUserPanel = filament()->getCurrentPanel()?->getId() === 'user';
@endphp

<div
    x-id="['input']"
    {{ $attributes->class(['fi-global-search-field']) }}
>
    <label x-bind:for="$id('input')" class="sr-only">
        {{ __('filament-panels::global-search.field.label') }}
    </label>

    <div class="fi-input-wrp flex items-center rounded-lg shadow-sm ring-1 ring-gray-950/10 dark:ring-white/20 bg-white dark:bg-white/5">
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

        {{-- CBIR camera & gallery buttons — only in user panel.
             Tidak lagi navigasi ke halaman cbir-search: tombol kamera membuka
             cbir-camera-options (dropdown) & tombol galeri membuka
             cbir-browse-modal + cbir-browse-options langsung di tempat. --}}
        @if($isUserPanel)
            @livewire(\App\Livewire\Shared\NativeCameraCbirButton\NativeCameraCbirButton::class)
        @endif

        {{-- Original suffix if any --}}
        @if($suffix)
            <div class="pe-3 text-sm text-gray-500 dark:text-gray-400 shrink-0">{{ $suffix }}</div>
        @endif
    </div>
</div>