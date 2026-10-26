{{-- Admin theme switcher: one selector with text and Light/Dark/System choices. --}}
@if (filament()->hasDarkMode() && (! filament()->hasDarkModeForced()))
    <div
        class="fi-theme-switcher"
        x-data="{ theme: null }"
        x-init="
            $watch('theme', () => $dispatch('theme-changed', theme))
            theme = localStorage.getItem('theme') || @js(filament()->getDefaultThemeMode()->value)
        "
    >
        <x-filament::dropdown
            placement="top-start"
            class="!divide-y-0"
            x-on:mouseenter="clearTimeout($el._hoverTimer); open($event)"
            x-on:mouseleave="$el._hoverTimer = setTimeout(() => close($event), 220)"
        >
            <x-slot name="trigger">
                <button
                    type="button"
                    aria-label="{{ __('Tema') }}"
                    class="fi-theme-switcher-btn flex h-10 items-center gap-2 rounded-md px-3 text-xs font-medium ring-1 ring-gray-950/10 transition hover:bg-gray-50 dark:ring-white/20 dark:hover:bg-white/5"
                >
                    @foreach ([
                        'light' => ['/images/icons/sun.svg', __('Light')],
                        'dark' => ['/images/icons/moon.svg', __('Dark')],
                        'system' => ['/images/icons/computer_desktop.svg', __('System')],
                    ] as $mode => [$icon, $label])
                        <template x-if="theme === '{{ $mode }}'">
                            <span class="flex items-center gap-2">
                                <x-filament::icon :icon="$icon" class="h-4 w-4" />
                                <span>{{ $label }}</span>
                            </span>
                        </template>
                    @endforeach
                </button>
            </x-slot>

            <x-filament::dropdown.list class="!w-[360px] !min-w-0 max-w-[calc(100vw-16px)] !border-0 !divide-y-0 !bg-white !text-gray-900 !ring-0 shadow-xl dark:!bg-gray-950 dark:!text-white">
                <div class="grid grid-cols-1 gap-1">
                @foreach ([
                    'light' => ['/images/icons/sun.svg', __('Light')],
                    'dark' => ['/images/icons/moon.svg', __('Dark')],
                    'system' => ['/images/icons/computer_desktop.svg', __('System')],
                ] as $mode => [$icon, $label])
                    <button
                        type="button"
                        x-on:click="theme = '{{ $mode }}'; close()"
                        class="flex w-full min-w-0 items-center justify-start gap-2 whitespace-nowrap rounded-md px-3 py-2 text-left text-sm !text-gray-900 outline-none transition hover:bg-gray-100 dark:!text-white dark:hover:bg-white/10"
                    >
                        <x-filament::icon :icon="$icon" class="h-5 w-5 shrink-0" />
                        <span>{{ $label }}</span>
                    </button>
                @endforeach
                </div>
            </x-filament::dropdown.list>
        </x-filament::dropdown>
    </div>
@endif
