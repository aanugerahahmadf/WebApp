{{--
    Dropdown theme switcher (Terang / Gelap / Sistem) — diekstrak dari
    topbar-auth-actions agar bisa dipakai ulang di sidebar mobile.
    Hand-rolled dari dropdown parts Filament (lihat komentar panjang di
    topbar-auth-actions). Memakai event 'theme-changed' yang sama.
--}}
@php
    $themeOptions = [
        'light' => 'heroicon-m-sun',
        'dark' => 'heroicon-m-moon',
        'system' => 'heroicon-m-computer-desktop',
    ];
@endphp

@if (filament()->hasDarkMode() && (! filament()->hasDarkModeForced()))
    <div
        class="fi-theme-switcher"
        x-data="{ theme: null }"
        x-init="
            $watch('theme', () => {
                $dispatch('theme-changed', theme)
            })

            theme = localStorage.getItem('theme') || @js(filament()->getDefaultThemeMode()->value)
        "
    >
        <x-filament::dropdown placement="bottom-end" teleport>
            <x-slot name="trigger">
                <button
                    type="button"
                    aria-label="{{ __('Tema') }}"
                    x-tooltip="{
                        content: @js(__('Tema')),
                        theme: $store.theme,
                    }"
                    class="fi-theme-switcher-btn flex items-center justify-center h-10 min-w-10 rounded-md ring-1 ring-gray-950/10 dark:ring-white/20 text-gray-500 hover:text-gray-600 dark:text-gray-400 dark:hover:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5 focus-visible:outline-none focus-visible:bg-gray-50 dark:focus-visible:bg-white/5 transition-all active:scale-95"
                >
                    @foreach ($themeOptions as $theme => $icon)
                        <template x-if="theme === '{{ $theme }}'">
                            <x-filament::icon :icon="$icon" class="h-5 w-5" />
                        </template>
                    @endforeach
                </button>
            </x-slot>

            <x-filament::dropdown.list>
                @foreach ($themeOptions as $theme => $icon)
                    <x-filament::dropdown.list.item
                        :icon="$icon"
                        x-on:click="(theme = '{{ $theme }}') && close()"
                        x-bind:class="theme === '{{ $theme }}' ? 'bg-gray-50 dark:bg-white/5 font-bold text-[#fbbf24]' : ''"
                    >
                        {{ __("filament-panels::layout.actions.theme_switcher.{$theme}.label") }}
                    </x-filament::dropdown.list.item>
                @endforeach
            </x-filament::dropdown.list>
        </x-filament::dropdown>
    </div>
@endif
