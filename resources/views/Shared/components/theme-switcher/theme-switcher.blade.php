{{--
    Dropdown theme switcher (Terang / Gelap / Sistem).

    Berada di Shared/ bukan di Welcome/ karena isinya benar-benar tanpa
    panel: tidak ada route, warna brand, atau teks panel-specific di sini --
    hanya `filament()->hasDarkMode()`, mode bawaan panel, dan event
    'theme-changed'. Ketiga panel memakai partial yang sama persis, jadi
    tidak ada salinan yang bisa saling ketinggalan.

    Dipakai dari dua tempat:
      - topbar, lewat Welcome.components.topbar-auth-actions -- hanya untuk
        tablet dan website desktop, lihat syaratnya di sana
      - sidebar, lewat render hook SIDEBAR_NAV_START tiap panel -- HP,
        mobile web, dan aplikasi desktop

    Ditulis ulang dari dropdown parts Filament, dan membaca event
    'theme-changed' yang sama seperti switcher bawaan Filament; file ini
    tidak pernah menyentuh localStorage sendiri.
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
                    <div class="list-none">
                        <button
                            type="button"
                            x-on:click="(theme = '{{ $theme }}') && close()"
                            @class([
                                'group flex items-center w-full gap-3 whitespace-nowrap rounded-md p-2 text-sm outline-none transition-all',
                                'text-gray-950 dark:text-white hover:bg-gray-50 dark:hover:bg-white/5',
                            ])
                        >
                            <x-filament::icon :icon="$icon" class="h-5 w-5 shrink-0" />

                            <span class="truncate flex-1 text-start" x-bind:class="theme === '{{ $theme }}' ? 'text-[#fbbf24] font-bold' : ''">
                                {{ ['light' => __('Light'), 'dark' => __('Dark'), 'system' => __('System')][$theme] }}
                            </span>
                        </button>
                    </div>
                @endforeach
            </x-filament::dropdown.list>
        </x-filament::dropdown>
    </div>
@endif
