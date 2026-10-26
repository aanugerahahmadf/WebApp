@props([
    'navigation',
])

<div
    style="position: fixed !important; top: env(safe-area-inset-top, 0px) !important; left: 0 !important; right: 0 !important; width: 100% !important; height: 4rem !important; min-height: 4rem !important; z-index: 40 !important; border: 0 !important; border-radius: 0 !important; box-shadow: none !important; background-color: transparent !important; background-image: none !important; -webkit-backdrop-filter: blur(8px) !important; backdrop-filter: blur(8px) !important; overflow: visible !important; overflow-x: visible !important;"
    {{
        $attributes->class([
            'fi-topbar sticky top-0 z-20 overflow-x-clip',
            'fi-topbar-with-navigation' => filament()->hasTopNavigation(),
        ])
    }}
>
    <style>
        .fi-panel-welcome .fi-topbar-item-label {
            font-family: 'Instrument Sans', sans-serif !important;
            font-size: 14px !important;
            font-weight: 400 !important;
            color: rgb(51 65 85) !important;
        }

        /* Light mode: topbar labels must be dark, not white. */
        html:not(.dark) .fi-panel-welcome .fi-topbar-item-label {
            color: rgb(15 23 42) !important;
        }

        .fi-panel-welcome .fi-topbar-group-label {
            font-size: 14px !important;
            font-weight: 600 !important;
            color: rgb(30 41 59) !important;
        }

        html:not(.dark) .fi-panel-welcome .fi-topbar-group-label {
            color: rgb(15 23 42) !important;
        }

        .dark .fi-panel-welcome .fi-topbar-item-label {
            color: rgb(203 213 225) !important;
        }

        .dark .fi-panel-welcome .fi-topbar-group-label {
            color: rgb(226 232 240) !important;
        }

        .fi-panel-welcome .fi-dropdown-list-item,
        .fi-panel-welcome .fi-dropdown-list-item * {
            font-family: 'Instrument Sans', sans-serif !important;
            font-size: 12px !important;
        }

        .fi-panel-welcome .fi-dropdown-list-item {
            color: rgb(51 65 85) !important;
            font-weight: 400 !important;
        }

        .dark .fi-panel-welcome .fi-dropdown-list-item,
        .dark .fi-panel-welcome .fi-dropdown-list-item-label {
            color: rgb(203 213 225) !important;
            font-weight: 400 !important;
        }
    </style>

    <nav
        style="height: 4rem !important; min-height: 4rem !important; border-radius: 0 !important; background-color: transparent !important; background-image: none !important; border: 0 !important; box-shadow: none !important; --tw-ring-color: transparent !important; --tw-ring-shadow: 0 0 #0000 !important; --tw-ring-offset-shadow: 0 0 #0000 !important; overflow: visible !important; overflow-x: visible !important;"
        class="flex h-16 items-center gap-x-4 bg-white px-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 md:px-6 lg:px-8"
    >
        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::TOPBAR_START) }}

        @php
            use App\Support\AppPlatform\AppPlatform;

            // Tombol sidebar: selalu tampilkan. Di ponsel membuka drawer navigasi
            // bawaan Filament; mulai tablet (768px) otomatis disembunyikan oleh
            // class 'lg:hidden' di bawah.
            //
            // APLIKASI DESKTOP: tombol tutup (X) di-sembunyikan. Di sana sidebar
            // adalah satu-satunya jalan ke navigasi -- topbar desktop app memakai
            // topNavigation, jadi item navigasinya disembunyikan oleh 'hidden md:flex'
            // dan tidak ada salinan lain di topbar. Kalau X di sini menutup sidebar,
            // pengguna dismissal navigasi dan tidak punya tombol untuk membukanya
            // lagi: jalan buntu. Tombol buka tetap dirender supaya sidebar yang
            // terlanjur tertutup (mis. state localStorage dari sesi sebelumnya)
            // masih bisa dikembalikan.
            $isDesktopApp = AppPlatform::isDesktopApp();
            $showSidebarToggle = filament()->hasNavigation();
        @endphp

        @if ($showSidebarToggle)
            <x-filament::icon-button
                color="gray"
                icon="/images/icons/menu.svg"
                icon-alias="panels::topbar.open-sidebar-button"
                icon-size="lg"
                :label="__('filament-panels::layout.actions.sidebar.expand.label')"
                x-cloak
                x-data="{}"
                x-on:click="$store.sidebar.open()"
                x-show="! $store.sidebar.isOpen"
                @class([
                    'fi-topbar-open-sidebar-btn',
                    'lg:hidden' => (! filament()->isSidebarFullyCollapsibleOnDesktop()) || filament()->isSidebarCollapsibleOnDesktop(),
                ])
            />

            @unless ($isDesktopApp)
                <x-filament::icon-button
                    color="gray"
                    icon="/images/icons/x.svg"
                    icon-alias="panels::topbar.close-sidebar-button"
                    icon-size="lg"
                    :label="__('filament-panels::layout.actions.sidebar.collapse.label')"
                    x-cloak
                    x-data="{}"
                    x-on:click="$store.sidebar.close()"
                    x-show="$store.sidebar.isOpen"
                    class="fi-topbar-close-sidebar-btn lg:hidden"
                />
            @endunless
        @endif

        @if (filament()->hasTopNavigation() || (! filament()->hasNavigation()))
            <div class="me-6 hidden lg:flex">
                @if ($homeUrl = filament()->getHomeUrl())
                    <a {{ \Filament\Support\generate_href_html($homeUrl) }}>
                        <x-filament-panels::logo />
                    </a>
                @else
                    <x-filament-panels::logo />
                @endif
            </div>

            @if (filament()->hasTenancy() && filament()->hasTenantMenu())
                <x-filament-panels::tenant-menu class="hidden lg:block" />
            @endif

            @if (filament()->hasNavigation())
                <ul class="me-4 hidden items-center gap-x-4 lg:flex">
                    @foreach ($navigation as $group)
                        @if ($groupLabel = $group->getLabel())
                            <x-filament::dropdown
                                placement="bottom-start"
                                x-on:mouseenter="open($event)"
                                x-on:mouseleave="close($event)"
                                :attributes="\Filament\Support\prepare_inherited_attributes($group->getExtraTopbarAttributeBag())"
                            >
                                <x-slot name="trigger">
                                    <x-filament-panels::topbar.item
                                        :active="$group->isActive()"
                                        :icon="$group->getIcon()"
                                    >
                                        {{ $groupLabel }}
                                    </x-filament-panels::topbar.item>
                                </x-slot>

                                @php
                                    $lists = [];

                                    foreach ($group->getItems() as $item) {
                                        if ($childItems = $item->getChildItems()) {
                                            $lists[] = [
                                                $item,
                                                ...$childItems,
                                            ];
                                            $lists[] = [];

                                            continue;
                                        }

                                        if (empty($lists)) {
                                            $lists[] = [$item];

                                            continue;
                                        }

                                        $lists[count($lists) - 1][] = $item;
                                    }

                                    if (empty($lists[count($lists) - 1])) {
                                        array_pop($lists);
                                    }
                                @endphp

                                @foreach ($lists as $list)
                                    <x-filament::dropdown.list>
                                        @foreach ($list as $item)
                                            @php
                                                $itemIsActive = $item->isActive();
                                            @endphp

                                            <x-filament::dropdown.list.item
                                                :badge="$item->getBadge()"
                                                :badge-color="$item->getBadgeColor()"
                                                :badge-tooltip="$item->getBadgeTooltip()"
                                                :color="$itemIsActive ? 'primary' : 'gray'"
                                                :href="$item->getUrl()"
                                                :icon="$itemIsActive ? ($item->getActiveIcon() ?? $item->getIcon()) : $item->getIcon()"
                                                tag="a"
                                                :target="$item->shouldOpenUrlInNewTab() ? '_blank' : null"
                                            >
                                                {{ $item->getLabel() }}
                                            </x-filament::dropdown.list.item>
                                        @endforeach
                                    </x-filament::dropdown.list>
                                @endforeach
                            </x-filament::dropdown>
                        @else
                            @foreach ($group->getItems() as $item)
                                <x-filament-panels::topbar.item
                                    :active="$item->isActive()"
                                    :active-icon="$item->getActiveIcon()"
                                    :badge="$item->getBadge()"
                                    :badge-color="$item->getBadgeColor()"
                                    :badge-tooltip="$item->getBadgeTooltip()"
                                    :icon="$item->getIcon()"
                                    :should-open-url-in-new-tab="$item->shouldOpenUrlInNewTab()"
                                    :url="$item->getUrl()"
                                >
                                    {{ $item->getLabel() }}
                                </x-filament-panels::topbar.item>
                            @endforeach
                        @endif
                    @endforeach
                </ul>
            @endif
        @endif

        <div
            @if (filament()->hasTenancy())
                x-persist="topbar.end.panel-{{ filament()->getId() }}.tenant-{{ filament()->getTenant()?->getKey() }}"
            @else
                x-persist="topbar.end.panel-{{ filament()->getId() }}"
            @endif
            class="ms-auto flex items-center gap-x-4"
        >
            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::GLOBAL_SEARCH_BEFORE) }}

            @if (filament()->isGlobalSearchEnabled())
                {{-- Tombol, bukan input: klik membuka modal pencarian
                     `app-global-search`. Lihat partial di bawah dan komentar
                     panjangnya (kenapa hanya SATU instance GlobalSearch). --}}
                @include('Shared.components.global-search-trigger.global-search-trigger')
            @endif

            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::GLOBAL_SEARCH_AFTER) }}

            @if (filament()->auth()->check())
                @php
                    // Unread count — direct query, no Livewire wrapper needed
                    $unreadCount = 0;
                    try {
                        $unreadCount = auth()->user()?->unreadNotifications()->count() ?? 0;
                    } catch (\Throwable) {}
                @endphp

                @if (filament()->hasDatabaseNotifications())
                    {{-- Bell only — modal DatabaseNotifications dirender oleh Filament (->databaseNotifications()) di BODY_END --}}
                    <div
                        class="fi-topbar-notif-wrap relative inline-flex shrink-0"
                        x-data="{
                            notifOpen: false,
                            unreadCount: {{ $unreadCount }},
                        }"
                        x-on:open-modal.window="if ($event.detail.id === 'database-notifications') notifOpen = true"
                        x-on:close-modal.window="if ($event.detail.id === 'database-notifications') notifOpen = false"
                        x-on:filament-notifications-database-unread-count-updated.window="
                            if (typeof $event.detail === 'number') unreadCount = $event.detail;
                            else if ($event.detail?.count !== undefined) unreadCount = $event.detail.count;
                        "
                    >
                        <button
                            type="button"
                            x-bind:class="notifOpen
                                ? 'fi-icon-btn fi-topbar-notif-active fi-topbar-database-notifications-btn relative flex items-center justify-center rounded-lg outline-none transition duration-75 h-9 w-9 shrink-0'
                                : 'fi-icon-btn fi-topbar-database-notifications-btn relative flex items-center justify-center rounded-lg outline-none transition duration-75 h-9 w-9 shrink-0 text-gray-400 hover:text-gray-500 dark:text-gray-500 dark:hover:text-gray-400'"
                            x-on:click="window.dispatchEvent(new CustomEvent('open-modal', { detail: { id: 'database-notifications' }, bubbles: true }))"
                            aria-label="{{ __('filament-panels::layout.actions.open_database_notifications.label') }}"
                        >
                            <x-filament::icon icon="/images/icons/bell.svg" class="fi-icon-btn-icon h-6 w-6" />

                            <span
                                x-show="unreadCount > 0"
                                x-text="unreadCount > 99 ? '99+' : unreadCount"
                                x-cloak
                                class="fi-topbar-notif-badge"
                                aria-hidden="true"
                            ></span>
                        </button>
                    </div>
                @endif


                {{-- Avatar/profil di header disembunyikan di ponsel & tablet:
                     profil lengkap ada di dasar sidebar (drawer). Di desktop
                     (>=1024px) sidebar tidak dipakai, jadi avatar kembali ke
                     topbar. --}}
                <div class="hidden lg:flex">
                    <x-filament-panels::user-menu />
                </div>
            @endif
        </div>

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::TOPBAR_END) }}
    </nav>
</div>
