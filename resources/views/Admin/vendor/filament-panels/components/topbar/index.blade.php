@props([
    'navigation',
])

<div
    {{--
        TOPBAR ADMIN: LATAR TRANSPARAN + BLUR (2026-10-26).

        Pencarian global sudah pindah ke SIDEBAR (di bawah logo), lonceng
        notifikasi sudah pindah ke sidebar, dan user menu topbar memang
        dimatikan. Jadi topbar tidak perlu latar berwarna.

        Latarnya tetap TRANSPARAN (tanpa --fi-chrome-bg), tapi efek blur
        DIKEMBALIKAN supaya konten yang bergulir di belakangnya tetap
        terbaca sebagai kaca -- resep yang sama dengan sidebar Admin:
        blur(12px) saturate(160%).

        `!important` di sini perlu karena public/css/panel-glass.css juga
        menulis background-color/backdrop-filter untuk
        `.fi-panel-admin .fi-topbar` dengan `!important`; inline style
        menang atas selector. Aturan global itu TIDAK diubah karena masih
        dipakai panel Welcome dan User.
    --}}
    style="position: fixed !important; top: env(safe-area-inset-top, 0px) !important; height: 4rem !important; min-height: 4rem !important; z-index: 40 !important; border: 0 !important; border-radius: 0 !important; box-shadow: none !important; background-color: transparent !important; background-image: none !important; -webkit-backdrop-filter: blur(12px) saturate(160%) !important; backdrop-filter: blur(12px) saturate(160%) !important; overflow: visible !important; overflow-x: visible !important;"
    x-bind:class="{ 'fi-admin-topbar-sidebar-open': $store.sidebar.isOpen }"
    {{
        $attributes->class([
            'fi-topbar sticky top-0 z-20 overflow-x-clip',
            'fi-topbar-with-navigation' => filament()->hasTopNavigation(),
        ])
    }}
    >
    <style>
        .fi-panel-admin .fi-topbar.fi-topbar {
            left: 0 !important;
            right: 0 !important;
            width: 100% !important;
        }

        .fi-panel-admin .fi-topbar.fi-topbar.fi-admin-topbar-sidebar-open {
            left: var(--sidebar-width) !important;
            width: calc(100% - var(--sidebar-width)) !important;
        }

        /* Ponsel: sidebar adalah drawer penuh layar di ATAS konten, jadi
           topbar tetap selebar layar. Mulai tablet (768px) sidebar menjadi
           kolom dan topbar bergeser ke kanannya. */
        @media (max-width: 1023px) {
            .fi-panel-admin .fi-topbar.fi-topbar.fi-admin-topbar-sidebar-open {
                left: 0 !important;
                width: 100% !important;
            }
        }
    </style>

    <nav
        style="height: 4rem !important; min-height: 4rem !important; border-radius: 0 !important; background-color: transparent !important; background-image: none !important; border: 0 !important; box-shadow: none !important; --tw-ring-color: transparent !important; --tw-ring-shadow: 0 0 #0000 !important; --tw-ring-offset-shadow: 0 0 #0000 !important; overflow: visible !important; overflow-x: visible !important;"
        class="flex h-16 items-center gap-x-4 bg-white px-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 md:px-6 lg:px-8"
    >
        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::TOPBAR_START) }}

        @php
            use App\Support\AppPlatform\AppPlatform;

            // Tombol sidebar: selalu tampilkan. Di mobile membuka drawer navigasi
            // bawaan Filament; di desktop (top navigation) otomatis disembunyikan
            // oleh class 'lg:hidden' di bawah.
            //
            // APLIKASI DESKTOP: tombol tutup (X) di-sembunyikan -- lihat catatan
            // lengkap di resources/views/Welcome/vendor/filament-panels/components/
            // topbar/index.blade.php. Ringkasnya: sidebar adalah satu-satunya jalan
            // ke navigasi di desktop app, jadi menutupnya dari topbar=buntu.
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

        @if (filament()->isGlobalSearchEnabled())
            <div class="fi-admin-topbar-search">
                <button
                    type="button"
                    data-admin-global-search-trigger
                    x-data
                    x-on:click="$dispatch('open-modal', { id: 'admin-global-search' })"
                    aria-label="{{ __('filament-panels::global-search.field.label') }}"
                    aria-haspopup="dialog"
                    class="fi-admin-global-search-trigger"
                >
                    <x-filament::icon alias="panels::global-search.field" icon="/images/icons/search.svg" class="h-5 w-5 shrink-0" />
                    <span class="fi-admin-global-search-trigger-label">
                        {{ __('filament-panels::global-search.field.placeholder') }}
                    </span>
                </button>
            </div>
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

            {{--
                PENCARIAN GLOBAL DIPINDAH KE SIDEBAR (2026-10-26).

                Search tadinya berdiri di kanan topbar. Permintaannya: pindah
                ke SIDEBAR, tepat DI BAWAH LOGO, supaya topbar bisa dibiarkan
                transparan tanpa latar (lihat inline style di atas).

                Markup lama TIDAK dihapus, hanya dimatikan lewat flag ini.
                Kalau dihidupkan lagi, cabut juga blok `.fi-sidebar-search`
                di override sidebar -- dua kotak pencarian untuk satu panel
                tidak boleh muncul bersamaan.
            --}}
            @php
                $topbarSearchEnabled = false;
            @endphp

            @if ($topbarSearchEnabled && filament()->isGlobalSearchEnabled())
                @livewire(Filament\Livewire\GlobalSearch::class)
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

                {{-- Lonceng topbar Admin DINONAKTIFKAN (2026-10-26).
                     Notifikasi database sekarang berdiri di SIDEBAR, di
                     samping language switcher (lihat override
                     sidebar/index.blade.php, blok `fi-sidebar-switchers`).

                     Blok lama TIDAK dihapus, hanya dimatikan lewat flag ini,
                     supaya mudah dikembalikan. Kalau flag dinyalakan lagi,
                     cabut juga @include di sidebar -- dua lonceng untuk satu
                     data tidak boleh muncul bersamaan. --}}
                @php
                    $topbarNotificationsEnabled = false;
                @endphp

                @if ($topbarNotificationsEnabled && filament()->hasDatabaseNotifications())
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
                            let prevCount = unreadCount;
                            if (typeof $event.detail === 'number') unreadCount = $event.detail;
                            else if ($event.detail?.count !== undefined) unreadCount = $event.detail.count;
                            if (unreadCount > prevCount) {
                                window.playNotificationSound?.();
                            }
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


                {{--
                    NONAKTIF (2026-10-25), JANGAN DIHAPUS: user menu di topbar
                    dimatikan, bukan dihapus. Blok profil (avatar + nama +
                    username + email, gaya Laravel Starter Kit) sekarang berada
                    di DASAR sidebar panel admin -- lihat override
                    resources/views/Admin/vendor/filament-panels/components/
                    sidebar/index.blade.php (blok `fi-sidebar-bawah`).

                    Flag disimpan supaya mudah dikembalikan. Kalau baris ini
                    dihidupkan lagi tanpa mematikan blok sidebar, profil muncul
                    DUA KALI di satu halaman.
                --}}
                @php
                    $topbarUserMenuEnabled = false;
                @endphp

                @if ($topbarUserMenuEnabled)
                    <x-filament-panels::user-menu />
                @endif
            @endif
        </div>

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::TOPBAR_END) }}
    </nav>
</div>
