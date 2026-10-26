@props([
    'navigation',
])

@php
    $openSidebarClasses = 'fi-sidebar-open w-[var(--sidebar-width)] translate-x-0 shadow-xl ring-1 ring-gray-950/5 dark:ring-white/10 rtl:-translate-x-0';
    $isRtl = __('filament-panels::layout.direction') === 'rtl';

    // Salinan sidebar Admin: switcher bahasa + tema dan blok profil berada
    // di DASAR sidebar, bukan sebaris dengan logo.
    $switchersAtSidebarBottom = true;
@endphp

{{--
    SIDEBAR USER & WELCOME -- SALINAN SIDEBAR ADMIN.

    Panel User dan Welcome memakai topNavigation(), jadi di desktop navigasi
    utama tetap berada di TOPBAR dan sidebar tampil sebagai drawer di
    ponsel/tablet. Topbar sengaja TIDAK diubah; yang disamakan dengan Admin
    hanyalah bentuk sidebar-nya:

      - header berisi logo + tombol kontrol (hamburger / tutup)
      - daftar navigasi dengan tipografi & warna yang sama dengan Admin
      - blok switcher + profil di ujung paling bawah

    CATATAN: file ini dipakai oleh DUA panel. resources/views/User/vendor
    di-prepend untuk panel 'user' maupun 'welcome' (AppServiceProvider, blok
    $registerVendorViews -- cabang welcome mendaftarkan folder 'User' dengan
    sengaja).
--}}
@if (\App\Support\TopNavigation\TopNavigation::hasChrome())

    {{--
        SIDEBAR DATAR: TIDAK ADA KAPSUL, TIDAK ADA OFFSET.

        Topbar datar di y=0, jadi sidebar juga mulai di y=0 dan menyambung ke
        tepi layar. Tipografi mengikuti sidebar Admin: item 11px regular,
        group 13px medium, dengan warna Light/Night yang sama.
    --}}
    @if (filament()->hasTopbar())
        <style>
            .fi-panel-user .fi-sidebar-item-label,
            .fi-panel-welcome .fi-sidebar-item-label,
            .fi-panel-user .fi-sidebar-group-label,
            .fi-panel-welcome .fi-sidebar-group-label {
                font-family: 'Instrument Sans', sans-serif !important;
                font-size: 11px !important;
                font-weight: 400 !important;
                color: rgb(51 65 85) !important;
            }

            .fi-panel-user .fi-sidebar-group-label,
            .fi-panel-welcome .fi-sidebar-group-label {
                color: rgb(30 41 59) !important;
                font-size: 13px !important;
                font-weight: 500 !important;
            }

            /* Badge navigasi harus identik dengan ukuran teks item menu.
               `getNavigationBadgeColor()` tetap mengatur warna; aturan ini
               hanya menyamakan tipografi badge dengan label sidebar. */
            .fi-panel-user .fi-sidebar-item .fi-badge,
            .fi-panel-user .fi-sidebar-item .fi-badge *,
            .fi-panel-welcome .fi-sidebar-item .fi-badge,
            .fi-panel-welcome .fi-sidebar-item .fi-badge * {
                font-family: 'Instrument Sans', sans-serif !important;
                font-size: 11px !important;
                line-height: 1rem !important;
                font-weight: 400 !important;
            }

            .fi-panel-user .fi-sidebar-item-button,
            .fi-panel-user .fi-sidebar-item-button .fi-sidebar-item-label,
            .fi-panel-user .fi-sidebar-group-button,
            .fi-panel-user .fi-sidebar-group-button .fi-sidebar-group-label,
            .fi-panel-welcome .fi-sidebar-item-button,
            .fi-panel-welcome .fi-sidebar-item-button .fi-sidebar-item-label,
            .fi-panel-welcome .fi-sidebar-group-button,
            .fi-panel-welcome .fi-sidebar-group-button .fi-sidebar-group-label {
                color: rgb(51 65 85) !important;
            }

            .dark .fi-panel-user .fi-sidebar-item-label,
            .dark .fi-panel-welcome .fi-sidebar-item-label {
                color: rgb(203 213 225) !important;
            }

            .dark .fi-panel-user .fi-sidebar-group-label,
            .dark .fi-panel-welcome .fi-sidebar-group-label {
                color: rgb(226 232 240) !important;
            }

            .dark .fi-panel-user .fi-sidebar-item-button,
            .dark .fi-panel-user .fi-sidebar-item-button .fi-sidebar-item-label,
            .dark .fi-panel-user .fi-sidebar-group-button,
            .dark .fi-panel-user .fi-sidebar-group-button .fi-sidebar-group-label,
            .dark .fi-panel-welcome .fi-sidebar-item-button,
            .dark .fi-panel-welcome .fi-sidebar-item-button .fi-sidebar-item-label,
            .dark .fi-panel-welcome .fi-sidebar-group-button,
            .dark .fi-panel-welcome .fi-sidebar-group-button .fi-sidebar-group-label {
                color: rgb(248 250 252) !important;
            }

            .fi-panel-user .fi-sidebar-item.fi-active .fi-sidebar-item-label,
            .fi-panel-welcome .fi-sidebar-item.fi-active .fi-sidebar-item-label {
                font-weight: 500 !important;
            }

            /* Sidebar: kolom penuh dari ATAS layar, tanpa kapsul. */
            .fi-panel-user .fi-sidebar,
            .fi-panel-welcome .fi-sidebar {
                position: fixed !important;
                top: 0 !important;
                bottom: 0 !important;
                height: auto !important;
                max-height: none !important;
                border-radius: 0 !important;
            }

            /* Tablet & desktop: panel ini memakai topNavigation(), jadi
               sidebar tidak dipakai -- navigasinya ada di topbar. Aturan ini
               menutup celah state drawer yang masih terbuka dari ponsel. */
            @media (min-width: 1024px) {
                .fi-panel-user .fi-sidebar,
                .fi-panel-welcome .fi-sidebar {
                    display: none !important;
                }
            }

            /* Ponsel & tablet kecil: drawer selebar layar seperti Admin. */
            @media (max-width: 1023px) {
                .fi-panel-user .fi-sidebar,
                .fi-panel-welcome .fi-sidebar {
                    width: 100vw !important;
                    max-width: 100vw !important;
                }
            }
        </style>
    @endif

{{-- format-ignore-start --}}
<aside
    x-data="{}"
    style="background-color: var(--fi-chrome-bg) !important; background-image: none !important; border: 0 !important; box-shadow: none !important; -webkit-backdrop-filter: blur(12px) saturate(160%) !important; backdrop-filter: blur(12px) saturate(160%) !important;"
    @if (filament()->isSidebarCollapsibleOnDesktop() && (! filament()->hasTopNavigation()))
        x-cloak
        x-bind:class="
            $store.sidebar.isOpen
                ? @js($openSidebarClasses . ' ' . 'lg:sticky')
                : '-translate-x-full rtl:translate-x-full lg:sticky lg:translate-x-0 rtl:lg:-translate-x-0'
        "
    @else
        @if (filament()->hasTopNavigation())
            x-cloak
            x-bind:class="$store.sidebar.isOpen ? @js($openSidebarClasses) : '-translate-x-full rtl:translate-x-full'"
        @elseif (filament()->isSidebarFullyCollapsibleOnDesktop())
            x-cloak
            x-bind:class="$store.sidebar.isOpen ? @js($openSidebarClasses . ' ' . 'lg:sticky') : '-translate-x-full rtl:translate-x-full'"
        @else
            x-cloak="-lg"
            x-bind:class="
                $store.sidebar.isOpen
                    ? @js($openSidebarClasses . ' ' . 'lg:sticky')
                    : 'w-[var(--sidebar-width)] -translate-x-full rtl:translate-x-full lg:sticky'
            "
        @endif
    @endif
    {{
        $attributes->class([
            'fi-sidebar fixed start-0 z-30 flex flex-col content-start bg-white transition-all dark:bg-gray-900 lg:z-0 lg:bg-transparent lg:shadow-none lg:ring-0 lg:transition-none dark:lg:bg-transparent',
            'lg:translate-x-0 rtl:lg:-translate-x-0' => ! (filament()->isSidebarCollapsibleOnDesktop() || filament()->isSidebarFullyCollapsibleOnDesktop() || filament()->hasTopNavigation()),
            'lg:-translate-x-full rtl:lg:translate-x-full' => filament()->hasTopNavigation(),
        ])
    }}
>
    <div class="overflow-x-clip">
        <header
            style="background-color: transparent !important; background-image: none !important; border: 0 !important; box-shadow: none !important; --tw-ring-color: transparent !important; --tw-ring-shadow: 0 0 #0000 !important; --tw-ring-offset-shadow: 0 0 #0000 !important;"
            class="fi-sidebar-header flex h-16 items-center bg-white px-6 ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 lg:shadow-sm"
        >
            {{--
                BARIS LOGO.

                Switcher bahasa + tema tidak lagi sebaris dengan logo:
                posisinya di DASAR sidebar, bersama blok profil. Posisi lama
                dinonaktifkan lewat $switchersAtSidebarBottom.
            --}}
            <div
                x-show="$store.sidebar.isOpen"
                x-transition:enter="lg:transition lg:delay-100"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                class="me-auto flex items-center gap-3"
            >
                @if ($homeUrl = filament()->getHomeUrl())
                    <a {{ \Filament\Support\generate_href_html($homeUrl) }}>
                        <x-filament-panels::logo />
                    </a>
                @else
                    <x-filament-panels::logo />
                @endif

                @unless ($switchersAtSidebarBottom)
                    <div class="fi-sidebar-switchers flex items-center gap-1.5">
                        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIDEBAR_NAV_START) }}
                    </div>
                @endunless
            </div>

            @if (filament()->isSidebarCollapsibleOnDesktop())
                <x-filament::icon-button
                    color="gray"
                    icon="/images/icons/menu.svg"
                    {{-- @deprecated Use `panels::sidebar.expand-button.rtl` instead of `panels::sidebar.expand-button` for RTL. --}}
                    :icon-alias="$isRtl ? ['panels::sidebar.expand-button.rtl', 'panels::sidebar.expand-button'] : 'panels::sidebar.expand-button'"
                    icon-size="lg"
                    :label="__('filament-panels::layout.actions.sidebar.expand.label')"
                    x-cloak
                    x-data="{}"
                    x-on:click="$store.sidebar.open()"
                    x-show="! $store.sidebar.isOpen"
                    class="mx-auto"
                />
            @endif

            @if (filament()->isSidebarCollapsibleOnDesktop() || filament()->isSidebarFullyCollapsibleOnDesktop())
                <x-filament::icon-button
                    color="gray"
                    icon="/images/icons/menu.svg"
                    {{-- @deprecated Use `panels::sidebar.collapse-button.rtl` instead of `panels::sidebar.collapse-button` for RTL. --}}
                    :icon-alias="$isRtl ? ['panels::sidebar.collapse-button.rtl', 'panels::sidebar.collapse-button'] : 'panels::sidebar.collapse-button'"
                    icon-size="lg"
                    :label="__('filament-panels::layout.actions.sidebar.collapse.label')"
                    x-cloak
                    x-data="{}"
                    x-on:click="$store.sidebar.close()"
                    x-show="$store.sidebar.isOpen"
                    class="fi-sidebar-collapse-btn ms-auto hidden lg:flex"
                />
            @endif

            {{-- Mobile close button: always shown on small screens regardless of collapsible config --}}
            <x-filament::icon-button
                color="gray"
                icon="/images/icons/x.svg"
                icon-alias="panels::sidebar.mobile-close-button"
                icon-size="lg"
                :label="__('filament-panels::layout.actions.sidebar.collapse.label')"
                x-data="{}"
                x-on:click="$store.sidebar.close()"
                class="fi-sidebar-mobile-close-btn ms-auto flex lg:hidden"
            />
        </header>
    </div>

    <nav
        class="fi-sidebar-nav flex-grow flex flex-col gap-y-7 overflow-y-auto overflow-x-hidden px-6 py-8"
        style="scrollbar-gutter: stable"
    >
        @if (filament()->hasTenancy() && filament()->hasTenantMenu())
            <div
                @class([
                    'fi-sidebar-nav-tenant-menu-ctn',
                    '-mx-2' => ! filament()->isSidebarCollapsibleOnDesktop(),
                ])
                @if (filament()->isSidebarCollapsibleOnDesktop())
                    x-bind:class="$store.sidebar.isOpen ? '-mx-2' : '-mx-4'"
                @endif
            >
                <x-filament-panels::tenant-menu />
            </div>
        @endif

        <ul class="fi-sidebar-nav-groups -mx-2 flex flex-col gap-y-7">
            @foreach ($navigation as $group)
                <x-filament-panels::sidebar.group
                    :active="$group->isActive()"
                    :collapsible="$group->isCollapsible()"
                    :icon="$group->getIcon()"
                    :items="$group->getItems()"
                    :label="$group->getLabel()"
                    :attributes="\Filament\Support\prepare_inherited_attributes($group->getExtraSidebarAttributeBag())"
                />
            @endforeach
        </ul>

        <script>
            var collapsedGroups = JSON.parse(
                localStorage.getItem('collapsedGroups'),
            )

            if (collapsedGroups === null || collapsedGroups === 'null') {
                localStorage.setItem(
                    'collapsedGroups',
                    JSON.stringify(@js(
                        collect($navigation)
                            ->filter(fn (\Filament\Navigation\NavigationGroup $group): bool => $group->isCollapsed())
                            ->map(fn (\Filament\Navigation\NavigationGroup $group): string => $group->getLabel())
                            ->values()
                            ->all()
                    )),
                )
            }

            collapsedGroups = JSON.parse(
                localStorage.getItem('collapsedGroups'),
            )

            document
                .querySelectorAll('.fi-sidebar-group')
                .forEach((group) => {
                    if (
                        !collapsedGroups.includes(group.dataset.groupLabel)
                    ) {
                        return
                    }

                    // Alpine.js loads too slow, so attempt to hide a
                    // collapsed sidebar group earlier.
                    group.querySelector(
                        '.fi-sidebar-group-items',
                    ).style.display = 'none'
                    group
                        .querySelector('.fi-sidebar-group-collapse-button')
                        .classList.add('-rotate-180')
                })
        </script>

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIDEBAR_NAV_END) }}
    </nav>

    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIDEBAR_FOOTER) }}

    {{--
        BLOK DASAR SIDEBAR -- SALINAN ADMIN.

        Profil dan switcher bahasa/tema berdiri di ujung paling bawah sidebar.
        `nav` di atasnya ber-flex-grow, jadi blok ini selalu terdorong ke
        dasar. `top-start` dipakai supaya dropdown membuka ke ATAS.
    --}}
    @if ($switchersAtSidebarBottom)
        <div
            x-show="$store.sidebar.isOpen"
            x-transition:enter="lg:transition lg:delay-100"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            class="fi-sidebar-bawah flex flex-col gap-3 border-t border-gray-950/5 px-6 pb-4 pt-4 dark:border-white/10"
            style="border-top: 0 !important;"
        >
            <div class="fi-sidebar-switchers flex items-center gap-1.5">
                {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIDEBAR_NAV_START) }}
            </div>

            @if (filament()->auth()->check())
                <x-filament-panels::user-menu placement="top-start" :profile-block="true" />
            @endif
        </div>
    @endif
</aside>
{{-- format-ignore-end --}}

@endif
